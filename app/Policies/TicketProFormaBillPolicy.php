<?php

namespace App\Policies;

use App\Models\TicketProFormaBill;
use App\Models\User;

class TicketProFormaBillPolicy
{
    public function view(User $user, TicketProFormaBill $bill): bool
    {
        return $user->is_superadmin || $user->selectedCompany()->id === $bill->company_id;
    }

    public function publish(User $user, TicketProFormaBill $bill): bool
    {
        return $user->is_superadmin;
    }

    public function download(User $user, TicketProFormaBill $bill): bool
    {
        return $user->is_superadmin || ($bill->is_approved == 1 && ($user->is_company_admin == 1) && ($user->selectedCompany()->id === $bill->company_id));
    }
}
