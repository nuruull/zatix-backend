<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TermAndCon extends Model
{
    use HasFactory;

    protected $fillable = ['content'];

    public function events()
    {
        return $this->hasMany(Event::class, 'tnc_id');
    }
}
