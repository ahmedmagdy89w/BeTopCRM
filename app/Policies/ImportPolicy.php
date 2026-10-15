<?php

namespace App\Policies;

use App\Models\Import;
use App\Models\User;

class ImportPolicy
{
    public function view(User $user, Import $import): bool
    {
        return $user->id === $import->user_id || $user->can('import-data');
    }
}
