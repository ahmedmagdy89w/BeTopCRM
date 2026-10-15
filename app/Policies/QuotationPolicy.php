<?php

namespace App\Policies;

use App\Models\Quotation;
use App\Models\User;
use App\Services\CompanySettingsService;
use Illuminate\Auth\Access\Response;

class QuotationPolicy
{
    public function viewAny(User $user): Response|bool
    {
        if (! CompanySettingsService::isQuotationsEnabled()) {
            return Response::deny(__('messages.quotations_disabled'));
        }

        return $user->can('view-quotations');
    }

    public function view(User $user, Quotation $quotation): Response|bool
    {
        if (! CompanySettingsService::isQuotationsEnabled()) {
            return Response::deny(__('messages.quotations_disabled'));
        }

        return $user->can('view-quotations')
            || $quotation->opportunity->assigned_to === $user->id
            || $quotation->created_by === $user->id;
    }

    public function create(User $user): Response|bool
    {
        if (! CompanySettingsService::isQuotationsEnabled()) {
            return Response::deny(__('messages.quotations_disabled'));
        }

        return $user->can('create-quotations');
    }

    public function send(User $user, Quotation $quotation): Response|bool
    {
        if (! CompanySettingsService::isQuotationsEnabled()) {
            return Response::deny(__('messages.quotations_disabled'));
        }

        return $user->can('send-quotations')
            || $quotation->opportunity->assigned_to === $user->id;
    }
}
