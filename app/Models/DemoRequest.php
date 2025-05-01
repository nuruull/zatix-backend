<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DemoRequest extends Model
{
    use HasFactory;

    protected $table = 'demo_requests';

    protected $fillable = ['user_id', 'eo_name', 'email', 'eo_description', 'event_name', 'event_description', 'audience_target', 'status', 'note'];

    public function user() {
        return $this->belongsTo(User::class);
    }
}
