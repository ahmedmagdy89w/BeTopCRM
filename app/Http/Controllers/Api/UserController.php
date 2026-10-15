<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Requests\User\UpdateUserRoleRequest;
use App\Http\Resources\User\AssignableUserResource;
use App\Http\Resources\User\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private UserService $userService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);
        $users = $this->userService->list($request->all());

        return response()->json(UserResource::collection($users)->response()->getData(true));
    }

    public function assignable(Request $request): JsonResponse
    {
        $this->authorize('viewAssignable', User::class);
        $users = $this->userService->assignable($request->only(['search', 'per_page']));

        return response()->json(AssignableUserResource::collection($users)->response()->getData(true));
    }

    public function showAssignable(User $user): AssignableUserResource
    {
        $this->authorize('viewAssignable', User::class);

        return new AssignableUserResource($this->userService->findAssignable($user->id));
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);
        $user = $this->userService->create($request->validated());

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        $this->authorize('view', $user);

        return new UserResource($user->loadCount(['assignedCustomers', 'managedCustomers']));
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $this->authorize('update', $user);
        $user = $this->userService->update($user, $request->validated());

        return new UserResource($user);
    }

    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);
        $this->userService->deactivate($user);

        return response()->json(null, 204);
    }

    public function updateRole(UpdateUserRoleRequest $request, User $user): UserResource
    {
        $this->authorize('update', $user);
        $user = $this->userService->updateRole($user, $request->role);

        return new UserResource($user);
    }

    public function activate(User $user): UserResource
    {
        $this->authorize('update', $user);
        $user = $this->userService->activate($user);

        return new UserResource($user);
    }
}
