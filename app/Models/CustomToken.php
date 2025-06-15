<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class CustomToken extends SanctumPersonalAccessToken
{
    public $table = 'personal_access_tokens';

    public function can($ability) {
        return $this->tokenable()->can($ability);
    }
}
