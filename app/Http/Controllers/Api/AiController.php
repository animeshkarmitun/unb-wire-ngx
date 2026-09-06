<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AiAssistRequest;
use App\Services\AiService;
use Illuminate\Http\JsonResponse;

class AiController extends Controller
{
    public function assist(string $kind, AiAssistRequest $request, AiService $svc): JsonResponse
    {
        $pack = $svc->call($kind, $request->validated(), $request->user()->id, $request->input('story_id'));

        return response()->json(['pack' => $pack]);
    }
}
