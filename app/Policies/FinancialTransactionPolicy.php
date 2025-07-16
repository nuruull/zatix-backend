<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Event;
use App\Models\FinancialTransaction;

class FinancialTransactionPolicy
{
    /**
     * Siapa yang boleh melihat daftar transaksi sebuah event?
     */
    public function viewAny(User $user, Event $event): bool
    {
        // Owner atau Finance dari EO event tersebut
        return $user->id === $event->eventOrganizer->eo_owner_id ||
            ($user->hasRole('finance') && $event->eventOrganizer->members()->where('user_id', $user->id)->exists());
    }

    /**
     * Siapa yang boleh melihat detail satu transaksi?
     */
    public function view(User $user, FinancialTransaction $transaction): bool
    {
        return $this->viewAny($user, $transaction->event);
    }

    /**
     * Siapa yang boleh membuat transaksi untuk sebuah event?
     */
    public function create(User $user, Event $event): bool
    {
        // Hanya Finance dari EO event tersebut yang boleh mencatat
        return $user->hasRole('finance') && $event->eventOrganizer->members()->where('user_id', $user->id)->exists();
    }

    /**
     * Siapa yang boleh mengupdate transaksi?
     */
    public function update(User $user, FinancialTransaction $transaction): bool
    {
        // Hanya Finance yang mencatatnya atau EO Owner dari event tersebut
        return $user->id === $transaction->recorded_by_user_id ||
            $user->id === $transaction->event->eventOrganizer->eo_owner_id;
    }

    /**
     * Siapa yang boleh menghapus transaksi?
     */
    public function delete(User $user, FinancialTransaction $transaction): bool
    {
        return $this->update($user, $transaction);
    }
}
