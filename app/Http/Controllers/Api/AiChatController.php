<?php

namespace App\Http\Controllers\Api;

use App\AI\AiChat;
use App\Http\Controllers\Controller;
use App\Http\Requests\AiChat\ChatAiRequest;
use App\Http\Resources\AiChat\AiChatSuggestedQuestionsResource;
use App\Http\Resources\AsyncJob\AsyncJobResource;
use App\Services\AiChatService;
use App\Services\AsyncJobService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class AiChatController extends Controller
{
    public function __construct(
        private AiChatService $aiChatService,
        private AsyncJobService $asyncJobService,
    ) {}

    public function chatAsync(ChatAiRequest $request): JsonResponse
    {
        $this->authorize('create', AiChat::class);

        $asyncJob = $this->asyncJobService->dispatch(
            AiChatService::class,
            'answerWithAi',
            [$request->validated(), $request->user()->id],
            $request->user(),
        );

        return (new AsyncJobResource($asyncJob))
            ->response()
            ->setStatusCode(201);
    }

    public function suggestedQuestions(Request $request): AiChatSuggestedQuestionsResource
    {
        $this->authorize('viewAny', AiChat::class);

        return new AiChatSuggestedQuestionsResource(
            $this->aiChatService->suggestedQuestions(App::getLocale())
        );
    }
}
