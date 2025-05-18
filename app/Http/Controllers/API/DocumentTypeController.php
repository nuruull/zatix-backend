<?php

namespace App\Http\Controllers\API;

use App\Models\DocumentType;
use Illuminate\Http\Request;
use App\Models\EventOrganizer;
use App\Enum\Status\DocumentTypeEnum;
use Illuminate\Validation\Rules\Enum;
use App\Http\Controllers\BaseController;
use Illuminate\Support\Facades\Validator;

class DocumentTypeController extends BaseController
{
    public function store(Request $request, $eo_id)
    {
        try {
            $eo = EventOrganizer::find($eo_id);

            if ($eo->eo_owner !== auth()->id()) {
                return $this->sendError(
                    "Unauthorized access to this EO",
                    [],
                    403
                );
            }

            $validator = Validator::make($request->all(), [
                'type' => ['required', new Enum(DocumentTypeEnum::class)],
            ]);

            if ($validator->fails()) {
                return $this->sendError(
                    'Validation Error',
                    $validator->errors(),
                    422
                );
            }

            $existingType = DocumentType::where('eo_id', $eo_id)
                ->where('type', $request->type)
                ->first();

            if ($existingType) {
                return $this->sendError(
                    'Document type already exists for this EO',
                    [],
                    409
                );
            }

            $documentType = DocumentType::create([
                'eo_id' => $eo_id,
                'type' => $request->type,
            ]);

            return $this->sendResponse(
                [
                    'document_type_id' => $documentType->id,
                    'type' => $documentType->type,
                ],
                'Document type created successfully.',
                201
            );
        } catch (\Exception $e) {
            return $this->sendError(
                'An error occurred',
                $e->getMessage(),
                500
            );
        }
    }
}
