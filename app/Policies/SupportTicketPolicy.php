<?php

namespace App\Policies;

use App\Actions\Admin\Impersonation;
use App\Models\SupportTicket;
use App\Models\User;

/**
 * Who can do what with support tickets:
 *  - super admin: sees and answers every ticket;
 *  - dono: sees and writes only tickets of their own restaurant;
 *  - nobody writes as a dono while a super admin is impersonating them.
 */
class SupportTicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, SupportTicket $ticket): bool
    {
        return $user->isSuperAdmin() || $this->ownsTicket($user, $ticket);
    }

    public function create(User $user): bool
    {
        return $user->isOwner() && $user->restaurant_id !== null && ! Impersonation::active();
    }

    public function reply(User $user, SupportTicket $ticket): bool
    {
        return $user->isSuperAdmin() || ($this->ownsTicket($user, $ticket) && ! Impersonation::active());
    }

    public function close(User $user, SupportTicket $ticket): bool
    {
        return $this->reply($user, $ticket);
    }

    public function reopen(User $user, SupportTicket $ticket): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, SupportTicket $ticket): bool
    {
        return false;
    }

    public function delete(User $user, SupportTicket $ticket): bool
    {
        return false;
    }

    private function ownsTicket(User $user, SupportTicket $ticket): bool
    {
        return $user->isOwner() && $user->restaurant_id !== null && $user->restaurant_id === $ticket->restaurant_id;
    }
}
