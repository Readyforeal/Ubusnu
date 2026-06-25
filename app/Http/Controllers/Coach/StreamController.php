<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\ChatThread;
use App\Services\Coach\ChatLoop;
use App\Services\Coach\CoachConfig;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StreamController extends Controller
{
    public function stream(Request $request, ChatThread $thread, ChatLoop $loop, CoachConfig $config): Response
    {
        abort_unless($thread->user_id === $request->user()->id, 403);

        $message = (string) $request->input('message', '');
        abort_if($message === '', 422, 'message is required');

        if (! $config->isConfigured()) {
            return response()->json(['error' => 'Coach is not configured'], 503);
        }

        return new StreamedResponse(function () use ($thread, $loop, $message) {
            // Tell PHP to push to the wire after every echo. Combined with the
            // X-Accel-Buffering header and FrankenPHP's default unbuffered
            // behavior, this is enough to deliver NDJSON chunks live without
            // touching Laravel's outer output buffer (which would error in
            // tests).
            ob_implicit_flush(true);

            foreach ($loop->run($thread, $message) as $event) {
                echo json_encode($event)."\n";
                flush();
            }
        }, 200, [
            'Content-Type' => 'application/x-ndjson',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
