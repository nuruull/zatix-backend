<?php

namespace App\Http\Controllers\API;

use App\Models\DocumentType;
use Illuminate\Http\Request;
use App\Traits\ManageFileTrait;
use App\Models\IndividualDocument;
use App\Models\OrganizationDocument;
use App\Enum\Status\DocumentTypeEnum;
use App\Enum\Status\DocumentStatusEnum;
use App\Http\Controllers\BaseController;
use Illuminate\Validation\ValidationException;

class OrganizationDocumentController extends BaseController
{
    use ManageFileTrait;
    public function store(Request $request, $id)
    {
        try {
            $documentType = DocumentType::findOrFail($id);

            if ($documentType->type !== DocumentTypeEnum::ORGANIZATION) {
                return $this->sendError(
                    'Invalid document type for this form',
                    [],
                    422
                );
            }

            $validated = $request->validate([
                'npwp_file' => 'required|file|mimes:pdf,jpg,jpeg,png',
                'npwp_number' => 'required|string|max:50',
                'npwp_name' => 'required|string|max:255',
                'npwp_address' => 'required|string',
                'nib_file' => 'required|file|mimes:pdf,jpg,jpeg,png',
                'nib_number' => 'required|string|max:50',
                'nib_name' => 'required|string|max:255',
                'nib_address' => 'required|string',
            ]);

            $npwpPath = $this->storeFile($request->file('npwp_file'), 'documents/npwp');
            $nibPath = $this->storeFile($request->file('nib_file'), 'documents/nib');

            $document = IndividualDocument::create([
                'doc_type_id' => $id,
                'npwp_file' => $npwpPath,
                'npwp_number' => $validated['npwp_number'],
                'npwp_name' => $validated['npwp_name'],
                'npwp_address' => $validated['npwp_address'],
                'nib_file' => $nibPath,
                'nib_number' => $validated['nib_number'],
                'nib_name' => $validated['nib_name'],
                'nib_address' => $validated['nib_address'],
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

    public function show($id)
    {
        try {
            $document = OrganizationDocument::where('doc_type_id', $id)->firstOrFail();
            return $this->sendResponse($document, 'Data dokumen organisasi ditemukan');
        } catch (\Throwable $th) {
            return $this->sendError('Data dokumen organisasi tidak ditemukan');
        }
    }

    public function listPending()
    {
        $pendingDocs = OrganizationDocument::where('status', DocumentStatusEnum::PENDING)->get();

        return $this->sendResponse(
            $pendingDocs,
            'List dokumen individu pending'
        );
    }

    public function verify(Request $request, $id)
    {
        try {
            $request->validate([
                'status' => 'required|in:verified,rejected',
                'rejected_reason' => 'nullable|string',
            ]);

            $document = OrganizationDocument::findOrFail($id);

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
