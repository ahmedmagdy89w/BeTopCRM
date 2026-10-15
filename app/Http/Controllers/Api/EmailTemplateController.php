<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmailTemplate\GenerateEmailTemplateRequest;
use App\Http\Requests\EmailTemplate\StoreEmailTemplateRequest;
use App\Http\Requests\EmailTemplate\UpdateEmailTemplateRequest;
use App\Http\Resources\AsyncJob\AsyncJobResource;
use App\Http\Resources\EmailTemplate\EmailTemplateResource;
use App\Models\EmailTemplate;
use App\Services\AsyncJobService;
use App\Services\EmailTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EmailTemplateController extends Controller
{
    public function __construct(
        private EmailTemplateService $templateService,
        private AsyncJobService $asyncJobService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', EmailTemplate::class);

        return EmailTemplateResource::collection($this->templateService->list($request->all()));
    }

    public function store(StoreEmailTemplateRequest $request): JsonResponse
    {
        $this->authorize('create', EmailTemplate::class);
        $template = $this->templateService->create($request->validated(), $request->user());

        return (new EmailTemplateResource($template))->response()->setStatusCode(201);
    }

    public function show(EmailTemplate $template): EmailTemplateResource
    {
        $this->authorize('view', $template);

        return new EmailTemplateResource($template);
    }

    public function update(UpdateEmailTemplateRequest $request, EmailTemplate $template): EmailTemplateResource
    {
        $this->authorize('update', $template);

        return new EmailTemplateResource($this->templateService->update($template, $request->validated()));
    }

    public function destroy(EmailTemplate $template): JsonResponse
    {
        $this->authorize('delete', $template);
        $this->templateService->delete($template);

        return response()->json(null, 204);
    }

    public function preview(Request $request, EmailTemplate $template): JsonResponse
    {
        $this->authorize('view', $template);
        $data = $this->templateService->preview($template, $request->input('data', []));

        return response()->json(['data' => $data]);
    }

    public function generate(GenerateEmailTemplateRequest $request): JsonResponse
    {
        $this->authorize('create', EmailTemplate::class);
        $asyncJob = $this->asyncJobService->dispatch(
            EmailTemplateService::class,
            'generateWithAi',
            [$request->validated()],
            $request->user(),
        );

        return (new AsyncJobResource($asyncJob))
            ->response()
            ->setStatusCode(201);
    }
}
