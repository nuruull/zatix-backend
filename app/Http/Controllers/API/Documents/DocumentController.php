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
use App\Actions\CheckEoVerificationStatus;
use App\Notifications\DocumentStatusUpdated;
use Illuminate\Validation\ValidationException;

class DocumentController extends BaseController
{
    use ManageFileTrait;
    public function store(Request $request)
    {
        $eventOrganizer = Auth::user()->eventOrganizer;

        // 2. Pastikan user sudah punya profil EO
        if (!$eventOrganizer) {
            return $this->sendError('You must create an Event Organizer profile before uploading documents.', [], 403);
        }

        // 3. Hapus 'event_organizer_id' dari validasi
        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(['ktp', 'npwp', 'nib'])],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
            'number' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:1000'],
        ]);

        try {
            return DB::transaction(function () use ($eventOrganizer, $validated, $request) {
                // Cek apakah dokumen dengan tipe ini sudah ada
                if ($eventOrganizer->documents()->where('type', $validated['type'])->exists()) {
                    return $this->sendError('A document of this type already exists for your profile.', [], 409);
                }

                // Simpan file
                $filePath = $this->storeFile($request->file('file'), 'documents/eo_' . $eventOrganizer->id);

                // Buat dokumen melalui relasi
                $document = $eventOrganizer->documents()->create([
                    'type' => $validated['type'],
                    'file' => $filePath,
                    'number' => $validated['number'],
                    'name' => $validated['name'],
                    'address' => $validated['address'],
                    'status' => DocumentStatusEnum::PENDING,
                ]);

                return $this->sendResponse($document, 'Document uploaded successfully.', 201);
            });
        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (Throwable $th) {
            DB::rollBack();
            Log::error('Error storing EO document: ' . $th->getMessage());
            return $this->sendError('Failed to save the document.', [], 500);
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

            (new CheckEoVerificationStatus)->execute($document->getRelationValue('documentable'));

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
