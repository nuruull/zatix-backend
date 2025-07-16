<?php

namespace App\Models;

use App\Enum\Type\FinancialTransactionTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinancialTransaction extends Model
{
    use HasFactory;

    protected $fillable = ['event_id', 'type', 'description', 'category', 'amount', 'transaction_date', 'proof_trans_url', 'recorded_by_user_id'];

    protected $casts = [
        'type' => FinancialTransactionTypeEnum::class,
        'amount' => 'decimal:2',
        'transaction_date' => 'date',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
