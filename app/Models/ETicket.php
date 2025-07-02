<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ETicket extends Model
{
    use HasFactory;

    /**
     * Nama tabel yang digunakan oleh model ini.
     *
     * @var string
     */
    protected $table = 'e_tickets';

    public $incrementing = true;
    protected $keyType = 'int';


    /**
     * Atribut yang bisa diisi secara massal.
     *
     * @var array
     */
    protected $fillable = [
        'ticket_code',
        'order_id',
        'user_id',
        'ticket_id',
        'attendee_name',
        'checked_in_at',
    ];

    /**
     * Tipe data native yang harus di-cast.
     *
     * @var array
     */
    protected $casts = [
        'checked_in_at' => 'datetime',
    ];

    /**
     * Mendapatkan data Order dari e-ticket ini.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    /**
     * Mendapatkan data User (pemilik) dari e-ticket ini.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mendapatkan data jenis Tiket dari e-ticket ini.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
