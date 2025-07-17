<?php

namespace App\Policies;

use App\Models\EventOrganizer;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EventOrganizerPolicy
{
    /**
     * Siapa yang boleh melihat daftar semua EO? (Hanya Super Admin)
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super-admin'. 'api');
    }

    /**
     * Siapa yang boleh melihat detail EO? (Super Admin)
     */
    public function view(User $user, EventOrganizer $eventOrganizer): bool
    {
        return $user->hasRole('super-admin', 'api');
    }

    /**
     * Siapa yang boleh membuat profil EO? (EO Owner yang belum punya profil)
     */
    public function create(User $user): bool
    {
        return $user->hasRole('eo-owner', 'api') && !$user->eventOrganizer()->exists();
    }

    /**
     * Siapa yang boleh mengupdate profil EO? (Hanya pemiliknya sendiri)
     */
    public function update(User $user, EventOrganizer $eventOrganizer): bool
    {
        return $user->id === $eventOrganizer->eo_owner_id;
    }
}
