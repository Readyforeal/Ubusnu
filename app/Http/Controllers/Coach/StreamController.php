<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\ChatThread;
use App\Services\Coach\ChatLoop;
use App\Services\Coach\CoachConfig;
use App\Services\Coach\ToolRegistry;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StreamController extends Controller
{
    public function stream(Request $request, ChatThread $thread, CoachConfig $config, ToolRegistry $registry): Response
    {
        abort_unless($thread->user_id === $request->user()->id, 403);

        $message = (string) $request->input('message', '');
        abort_if($message === '', 422, 'message is required');

        if (! $config->isConfigured()) {
            return response()->json(['error' => 'Coach is not configured'], 503);
        }

        $loop = new ChatLoop($config->driver(), $registry, $config);

        return new StreamedResponse(function () use ($thread, $loop, $message) {
            @set_time_limit(0);
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
