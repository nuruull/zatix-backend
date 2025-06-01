<?php

namespace App\Http\Controllers\API;

use Exception;
use App\Models\DocumentType;
use Illuminate\Http\Request;
use App\Traits\ManageFileTrait;
use App\Models\IndividualDocument;
use App\Enum\Type\DocumentTypeEnum;
use App\Enum\Status\DocumentStatusEnum;
use App\Http\Controllers\BaseController;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class IndividualDocumentController extends BaseController
{
    use ManageFileTrait;

    public function show($id)
    {
        try {
            $document = IndividualDocument::where('doc_type_id', $id)->firstOrFail();
            return $this->sendResponse($document, 'Data dokumen individu ditemukan');
        } catch (\Throwable $th) {
            return $this->sendError('Data dokumen individu tidak ditemukan');
        }
    }

    public function store(Request $request, $id)
    {
        try {
            $documentType = DocumentType::findOrFail($id);

            if ($documentType->type !== DocumentTypeEnum::INDIVIDUAL) {
                return $this->sendError(
                    'Invalid document type for this form',
                    [],
                    422
                );
            }

            $validated = $request->validate([
                'ktp_file' => 'required|file|mimes:pdf,jpg,jpeg,png',
                'ktp_number' => 'required|string|max:50',
                'ktp_name' => 'required|string|max:255',
                'ktp_address' => 'required|string',
                'npwp_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png',
                'npwp_number' => 'nullable|string|max:50',
                'npwp_name' => 'nullable|string|max:255',
                'npwp_address' => 'nullable|string',
            ]);

            $ktpPath = $this->storeFile($request->file('ktp_file'), 'documents/ktp');
            $npwpPath = null;
            if ($request->hasFile('npwp_file')) {
                $npwpPath = $this->storeFile($request->file('npwp_file'), 'documents/npwp');
            }

            $document = IndividualDocument::create([
                'doc_type_id' => $id,
                'ktp_file' => $ktpPath,
                'ktp_number' => $validated['ktp_number'],
                'ktp_name' => $validated['ktp_name'],
                'ktp_address' => $validated['ktp_address'],
                'npwp_file' => $npwpPath,
                'npwp_number' => $validated['npwp_number'] ?? null,
                'npwp_name' => $validated['npwp_name'] ?? null,
                'npwp_address' => $validated['npwp_address'] ?? null,
            ]);

            return $this->sendResponse(
                $document,
                'Individual document uploaded successfully',
                201
            );
        } catch (ValidationException $e) {
            return $this->sendError(
                'Validation failed',
                $e->errors(),
                422
            );
        } catch (\Exception $e) {
            return $this->sendError(
                'Failed to save the document',
                $e->getMessage(),
                500
            );
        }
    }

    //super admin
    public function listPending()
    {
        try {
            $pendingDocs = IndividualDocument::where('status', DocumentStatusEnum::PENDING)->get();

            if (!$pendingDocs->isEmpty()) {
                return $this->sendResponse(
                    [],
                    'No pending documents found.',
                    200
                );
            }

            return $this->sendResponse(
                $pendingDocs,
                'Pending individual documents retrieved successfully.'
            );
        } catch (Exception $e) {
            Log::error('Error retrieving pending documents: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return $this->sendError(
                'Failed to retrieve the list of pending documents.',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    //super admin
    public function verify(Request $request, $id)
    {
        try {
            $request->validate([
                'status' => 'required|in:verified,rejected',
                'rejected_reason' => 'nullable|string',
            ]);

            $document = IndividualDocument::findOrFail($id);

            $document->status = $request->status;
            $document->rejected_reason = $request->status === 'rejected'
                ? $request->rejected_reason
                : null;

            $document->save();

            return $this->sendResponse($document, 'Status dokumen berhasil diperbarui');
        } catch (\Throwable $th) {
            return $this->sendError('Gagal memverifikasi dokumen');
        }
    }

}
