<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IndividualDocument extends Model
{
    use HasFactory;
    protected $fillable = ['doc_type_id', 'ktp_file', 'ktp_number', 'ktp_name', 'ktp_address', 'npwp_file', 'npwp_number', 'npwp_name', 'npwp_address'];

    public function documentType(){
        return $this->belongsTo(DocumentType::class);
    }
}
