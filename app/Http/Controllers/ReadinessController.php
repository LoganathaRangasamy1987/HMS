<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ReadinessController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(): JsonResponse
    {
        $checks = [];
        try {
            $started = hrtime(true);
            DB::select('SELECT 1');
            $checks['database'] = ['ok' => true, 'latency_ms' => round((hrtime(true) - $started) / 1_000_000, 2)];
        } catch (Throwable) {
            $checks['database'] = ['ok' => false];
        }
        try {
            $key = 'readiness:'.bin2hex(random_bytes(8));
            Cache::put($key, 'ok', 10);
            $checks['cache'] = ['ok' => Cache::pull($key) === 'ok'];
        } catch (Throwable) {
            $checks['cache'] = ['ok' => false];
        }
        $checks['queue'] = ['ok' => Schema::hasTable('jobs') && Schema::hasTable('failed_jobs')];
        $checks['storage'] = ['ok' => is_writable(storage_path('framework')) && is_writable(storage_path('logs'))];
        $ready = collect($checks)->every(fn (array $check): bool => $check['ok']);

        return response()->json(['status' => $ready ? 'ready' : 'not_ready', 'checks' => $checks], $ready ? 200 : 503);
    }
}
