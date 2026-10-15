<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserSettings\ChangePasswordRequest;
use App\Http\Requests\UserSettings\UpdateSettingsRequest;
use App\Http\Requests\UserSettings\UploadAvatarRequest;
use App\Http\Resources\UserSettings\UserSettingsResource;
use App\Services\UserSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserSettingsController extends Controller
{
    public function __construct(private UserSettingsService $userSettingsService) {}

    public function show(Request $request): UserSettingsResource
    {
        return new UserSettingsResource($this->userSettingsService->show($request->user()));
    }

    public function update(UpdateSettingsRequest $request): UserSettingsResource
    {
        $user = $this->userSettingsService->update($request->user(), $request->validated());
        return new UserSettingsResource($user);
    }

    public function uploadAvatar(UploadAvatarRequest $request): UserSettingsResource
    {
        $user = $this->userSettingsService->uploadAvatar($request->user(), $request->file('avatar'));
        return new UserSettingsResource($user);
    }

    public function deleteAvatar(Request $request): JsonResponse
    {
        $this->userSettingsService->deleteAvatar($request->user());
        return response()->json(['message' => 'Avatar removed.']);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->userSettingsService->changePassword($request->user(), $request->password);
        return response()->json(['message' => 'Password updated.']);
    }
}
