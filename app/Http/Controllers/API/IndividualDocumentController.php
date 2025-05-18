<?php

namespace App\Http\Controllers\API;

use App\Enum\Status\DocumentTypeEnum;
use App\Http\Controllers\BaseController;
use App\Models\DocumentType;
use Illuminate\Http\Request;

class IndividualDocumentController extends BaseController
{
    public function store(Request $request, $doc_type_id)
    {
        $documentType = DocumentType::findOrFail($doc_type_id);

        if ($documentType->type !== DocumentTypeEnum::INDIVIDUAL) {
            return $this->sendError(
                'Invalid document type for this form'
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

    }
}
