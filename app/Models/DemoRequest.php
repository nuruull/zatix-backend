<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DemoRequest extends Model
{
    use HasFactory;

    protected $table = 'demo_requests';

    protected $fillable = [
        'user_id',
        'eo_name',
        'eo_email',
        'eo_description',
        'event_name',
        'event_description',
        'audience_target',
        'status',
        'note',
        'pitching_schedule',
        'pitching_link',
        'demo_access_expiry',
        'is_continue',
        'current_step',
        'rejected_reason',
        'role_updated'
    ];

    protected $casts = [
        'demo_access_expiry' => 'datetime',
        'pitching_schedule' => 'datetime',
        'is_continue' => 'boolean',
        'role_updated' => 'boolean'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getCurrentStepName()
    {
        $steps = [
            1 => 'Initial Request',
            2 => 'Pitching Schedule',
            3 => 'Pitching Approval',
            4 => 'Demo Account',
            5 => 'Role Upgrade'
        ];

        return $steps[$this->current_step] ?? 'Unknown Step';
    }

    public function canProceedTo($step)
    {
        switch ($step) {
            case 2:
                return $this->status === 'approved';
            case 3:
                return !empty($this->pitching_schedule);
            case 4:
                return !empty($this->pitching_link);
            case 5:
                return !empty($this->demo_access_expiry) && $this->is_continue;
        }

        return true;
    }
}
