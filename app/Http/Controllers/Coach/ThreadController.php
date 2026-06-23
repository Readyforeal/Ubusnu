<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\ChatThread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ThreadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $thread = ChatThread::create([
            'user_id' => $request->user()->id,
            'title' => 'New chat',
            'last_message_at' => now(),
        ]);

        return response()->json(['id' => $thread->id]);
    }
}
