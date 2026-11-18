<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\EvaluationNotificationService;
use Illuminate\Http\{JsonResponse, Request};

class EvaluationNotificationController extends Controller
{
    public function index(Request $request, EvaluationNotificationService $notifications): JsonResponse
    {
        $data = $request->validate(['page' => ['nullable', 'integer', 'min:1']]);
        return response()->json($notifications->inbox($request->user(), $data['page'] ?? 1))->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, string $notification, EvaluationNotificationService $notifications): JsonResponse
    {
        $data = $request->validate(['read' => ['required', 'boolean']]);
        $notifications->setRead($request->user(), $notification, (bool) $data['read']);
        return response()->json(['ok' => true])->header('Cache-Control', 'private, no-store');
    }
}
