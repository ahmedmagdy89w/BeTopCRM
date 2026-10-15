<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-products');
    }

    public function view(User $user, Product $product): bool
    {
        return $user->can('view-products');
    }

    public function create(User $user): bool
    {
        return $user->can('create-products');
    }

    public function update(User $user, Product $product): bool
    {
        return $user->can('update-products');
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->can('delete-products');
    }
}
