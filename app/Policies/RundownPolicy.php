<?php

namespace App\Policies;

use App\Models\Event;
use Illuminate\Auth\Access\Response;
use App\Models\Rundown;
use App\Models\User;

class RundownPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user, Event $event): bool
    {
        return $user->id === $event->eventOrganizer->eo_owner_id ||
            $event->eventOrganizer->members()->where('user_id', $user->id)->exists();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Rundown $rundown): bool
    {
        return $this->viewAny($user, $rundown->event);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Event $event): bool
    {
        return $user->id === $event->eventOrganizer->eo_owner_id;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Rundown $rundown): bool
    {
        return $user->id === $rundown->event->eventOrganizer->eo_owner_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Rundown $rundown): bool
    {
        return $this->update($user, $rundown);
    }
}
