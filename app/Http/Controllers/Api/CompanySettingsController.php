<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompanySettings\UpdateCompanySettingsRequest;
use App\Http\Requests\CompanySettings\UploadCompanyLogoRequest;
use App\Http\Resources\CompanySettings\CompanySettingResource;
use App\Http\Resources\CompanySettings\RevealedAiApiKeyResource;
use App\Models\CompanySetting;
use App\Services\CompanySettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanySettingsController extends Controller
{
    public function __construct(private CompanySettingsService $companySettingsService) {}

    public function show(Request $request): CompanySettingResource
    {
        $this->authorize('view', CompanySetting::class);

        return new CompanySettingResource($this->companySettingsService->get());
    }

    public function revealAiApiKey(): RevealedAiApiKeyResource
    {
        $this->authorize('update', CompanySetting::class);

        return new RevealedAiApiKeyResource([
            'api_key' => $this->companySettingsService->revealAiApiKey(),
        ]);
    }

    public function update(UpdateCompanySettingsRequest $request): CompanySettingResource
    {
        $this->authorize('update', CompanySetting::class);

        return new CompanySettingResource($this->companySettingsService->update($request->validated()));
    }

    public function uploadLogo(UploadCompanyLogoRequest $request): CompanySettingResource
    {
        $this->authorize('update', CompanySetting::class);

        return new CompanySettingResource($this->companySettingsService->uploadLogo($request->file('logo')));
    }

    public function deleteLogo(Request $request): JsonResponse
    {
        $this->authorize('update', CompanySetting::class);
        $this->companySettingsService->deleteLogo();

        return response()->json(['message' => __('messages.company_logo_deleted')]);
    }
}
