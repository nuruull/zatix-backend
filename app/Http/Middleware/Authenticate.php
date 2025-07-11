<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        // Jika request mengharapkan JSON (seperti dari API), jangan redirect.
        // Sebaliknya, biarkan exception handler yang akan mengembalikan respons 401.
        if ($request->expectsJson()) {
            return null;
        }

        // Jika bukan request API, biarkan perilaku default.
        return route('login');
    }
}
