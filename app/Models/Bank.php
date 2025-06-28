<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bank extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code', 'type', 'main_image', 'secondary_image'];

    public function paymentMethods()
    {
        return $this->hasMany(PaymentMethod::class);
    }
}
