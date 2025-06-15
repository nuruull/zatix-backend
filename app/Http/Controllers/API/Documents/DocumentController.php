<?php

namespace App\Http\Controllers\API\Documents;

use Throwable;
use App\Models\User;
use App\Models\Document;
use Illuminate\Http\Request;
use App\Models\EventOrganizer;
use App\Traits\ManageFileTrait;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Enum\Status\DocumentStatusEnum;
use App\Http\Controllers\BaseController;
use App\Notifications\DocumentStatusUpdated;
use Illuminate\Support\Facades\Notification;
use App\Notifications\NewVerificationRequest;
use Illuminate\Validation\ValidationException;

class DocumentController extends BaseController
{
    use ManageFileTrait;
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $user = Auth::user();

            $eventOrganizer = $user->eventOrganizer;

            if (!$eventOrganizer) {
                DB::rollback();
                return $this->sendError(
                    "Event Organizer is invalid or not found for this user.",
                    [],
                    403
                );
            }

            $allowedEoDocumentTypes = ['ktp', 'npwp', 'nib'];
            $validated = $request->validate([
                'type' => ['required', 'string', Rule::in($allowedEoDocumentTypes)],
                'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'], // Max 2MB
                'number' => ['required', 'string', 'max:255'],
                'name' => ['required', 'string', 'max:255'],
                'address' => ['required', 'string', 'max:1000'],
            ]);

            $filePath = null;
            if ($request->hasFile('file')) {
                $folder = 'documents/eo_' . $eventOrganizer->id . 'type/_' . $validated['type'];
                $filePath = $this->storeFile($request->file('file'), $folder);
                if (!$filePath) {
                    DB::rollback();
                    return $this->sendError('Failed to store the uploaded file.', [], 500);
                }
            }

            $document = $eventOrganizer->documents()->create([
                'type' => $validated['type'],
                'file' => $filePath,
                'number' => $validated['number'],
                'name' => $validated['name'],
                'address' => $validated['address'],
                'status' => DocumentStatusEnum::PENDING,
            ]);

            $superAdmins = User::role('super-admin')->get();
            if ($superAdmins->isNotEmpty()) {
                Notification::send($superAdmins, new NewVerificationRequest($document));
            }

            $document->load('documentable');
            DB::commit();

            return $this->sendResponse(
                $document,
                'Document successfully uploaded and awaiting verification.',
                201
            );
        } catch (ValidationException $e) {
            DB::rollback();
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (Throwable $th) {
            DB::rollback();
            Log::error('Error storing EO document: ' . $th->getMessage(), [
                'trace' => $th->getTraceAsString(),
                'user_id' => Auth::id(),
                'event_organizer_id' => $eventOrganizer->id ?? null,
                'request' => $request->all()
            ]);
            return $this->sendError('Failed to save the document.', ['detail' => $th->getMessage()], 500);
        }
    }

    public function index(Request $request)
    {
        try {
            $query = Document::query();

            $statusFilterInput = $request->input('status');

            if ($statusFilterInput) {
                if ($statusFilterInput === 'all') {
                } else {
                    $statusEnum = DocumentStatusEnum::tryFrom($statusFilterInput);
                    if ($statusEnum) {
                        $query->where('status', $statusEnum);
                    } else {
                        return $this->sendError(
                            "Invalid filter status value: {$statusFilterInput}",
                            [],
                            400
                        );
                    }
                }
            } else {
                $query->where('status', DocumentStatusEnum::PENDING);
            }

            $documents = $query->with('documentable')->latest()->paginate($request->input('per_page', 15));

            if ($documents->isEmpty() && $statusFilterInput) {
                return $this->sendResponse($documents, 'No documents found matching the criteria.');
            }
            if ($documents->isEmpty() && !$statusFilterInput) {
                return $this->sendResponse($documents, 'No pending documents found.');
            }

            return $this->sendResponse(
                $documents,
                'Document data retrieved successfully'
            );
        } catch (Throwable $th) {
            Log::error('Error retrieving document list: ' . $th->getMessage(), [
                'trace' => $th->getTraceAsString(),
                'filters' => $request->all()
            ]);
            return $this->sendError('Failed to retrieve document list.', ['detail' => $th->getMessage()], 500);
        }
    }

    public function show(Document $document)
    {
        try {
            $document->load('documentable');
            return $this->sendResponse($document, 'Document data found.');
        } catch (Throwable $th) {
            Log::error('Error displaying document details: ' . $th->getMessage(), [
                'trace' => $th->getTraceAsString(),
                'document_id' => $document->id ?? null
            ]);
            return $this->sendError('Failed to display document details.', ['detail' => $th->getMessage()], 500);
        }
    }

    public function updateStatus(Request $request, Document $document)
    {
        DB::beginTransaction();
        try {
            $validStatusValues = array_column(DocumentStatusEnum::cases(), 'value');
            $validated = $request->validate([
                'status' => ['required', Rule::in($validStatusValues)],
                'reason_rejected' => [
                    'nullable',
                    'string',
                    'max:1000',
                    Rule::requiredIf(fn() => $request->input('status') === DocumentStatusEnum::REJECTED->value),
                ],
            ]);

            $statusEnumToSet = DocumentStatusEnum::from($validated['status']);
            $document->status = $statusEnumToSet;

            if ($statusEnumToSet === DocumentStatusEnum::REJECTED) {
                $document->reason_rejected = $validated['reason_rejected'];
            } else {
                $document->reason_rejected = null;
            }

            $document->save();

            $document->load('documentable');

            $eoOwner = $document->documentable->eo_owner;
            if ($eoOwner) {
                $eoOwner->notify(new DocumentStatusUpdated($document));
            }

            DB::commit();

            return $this->sendResponse($document, 'Document status updated successfully.');

        } catch (ValidationException $e) {
            DB::rollback();
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (Throwable $th) {
            DB::rollback();
            Log::error('Error updating document status: ' . $th->getMessage(), [
                'trace' => $th->getTraceAsString(),
                'document_id' => $document->id,
                'request' => $request->all()
            ]);
            return $this->sendError('Failed to update document status.', ['detail' => $th->getMessage()], 500);
        }
    }
}
