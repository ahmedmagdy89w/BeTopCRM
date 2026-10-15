<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use App\Services\CompanySettingsService;
use Illuminate\Auth\Access\Response;

class InvoicePolicy
{
    public function viewAny(User $user): Response|bool
    {
        if (! CompanySettingsService::isInvoicesEnabled()) {
            return Response::deny(__('messages.invoices_disabled'));
        }

        return $user->can('view-invoices');
    }

    public function view(User $user, Invoice $invoice): Response|bool
    {
        if (! CompanySettingsService::isInvoicesEnabled()) {
            return Response::deny(__('messages.invoices_disabled'));
        }

        return $user->can('view-invoices')
            || $invoice->opportunity->assigned_to === $user->id
            || $invoice->created_by === $user->id;
    }

    public function create(User $user): Response|bool
    {
        if (! CompanySettingsService::isInvoicesEnabled()) {
            return Response::deny(__('messages.invoices_disabled'));
        }

        return $user->can('create-invoices');
    }
}
