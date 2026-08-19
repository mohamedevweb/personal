<?php

namespace App\Http\Controllers;

use App\Services\Chat\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __invoke(Request $request, ChatService $chat): JsonResponse
    {
        $data = $request->validate([
            'messages' => ['required', 'array', 'min:1', 'max:40'],
            'messages.*.role' => ['required', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'max:4000'],
        ]);

        // The model expects the conversation to end on the user's turn.
        abort_unless(end($data['messages'])['role'] === 'user', 422, 'The last message must be from the user.');

        return response()->json([
            'reply' => $chat->reply($request->user(), array_values($data['messages'])),
        ]);
    }
}
