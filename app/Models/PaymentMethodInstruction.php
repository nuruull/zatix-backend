<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethodInstruction extends Model
{
    use HasFactory;

    protected $table = 'payment_method_instructions';

    protected $fillable = ['bank_id', 'name', 'instructions'];
}
