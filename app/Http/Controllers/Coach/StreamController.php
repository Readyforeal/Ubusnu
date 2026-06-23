<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\ChatThread;
use App\Services\Coach\ChatLoop;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StreamController extends Controller
{
    public function stream(Request $request, ChatThread $thread, ChatLoop $loop): StreamedResponse
    {
        abort_unless($thread->user_id === $request->user()->id, 403);

        $message = (string) $request->input('message', '');
        abort_if($message === '', 422, 'message is required');

        return new StreamedResponse(function () use ($thread, $loop, $message) {
            foreach ($loop->run($thread, $message) as $event) {
                echo json_encode($event)."\n";
                ob_flush();
                flush();
            }
        }, 200, [
            'Content-Type' => 'application/x-ndjson',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
