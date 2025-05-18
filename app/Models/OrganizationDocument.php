<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationDocument extends Model
{
    use HasFactory;

        protected $fillable = [
            'doc_type_id',
            'npwp_file',
            'npwp_number',
            'npwp_name',
            'npwp_address',
            'nib_file',
            'nib_number',
            'nib_name',
            'nib_address',
        ];

        public function documentType() {
            return $this->belongsTo(DocumentType::class);
        }

}
