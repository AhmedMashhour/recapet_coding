<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class HealthController extends Controller
{

    public function health(): JsonResponse
    {
        $checks = [
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
            'services' => [],
        ];

        try {
            DB::connection()->getPdo();
            $checks['services']['database'] = [
                'status' => 'healthy',
                'response_time' => $this->measureResponseTime(function () {
                    DB::select('SELECT 1');
                }),
            ];
        } catch (\Exception $e) {
            $checks['services']['database'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
            $checks['status'] = 'unhealthy';
        }

        try {
            $key = 'health_check_' . time();
            Cache::put($key, true, 10);
            $value = Cache::get($key);
            Cache::forget($key);

            $checks['services']['cache'] = [
                'status' => $value === true ? 'healthy' : 'unhealthy',
                'driver' => config('cache.default'),
            ];
        } catch (\Exception $e) {
            $checks['services']['cache'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
            $checks['status'] = 'unhealthy';
        }

        try {
            $checks['services']['storage'] = [
                'status' => 'healthy',
                'writable' => is_writable(storage_path()),
            ];
        } catch (\Exception $e) {
            $checks['services']['storage'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
            $checks['status'] = 'unhealthy';
        }

        $statusCode = $checks['status'] === 'healthy' ? 200 : 503;

        return response()->json($checks, $statusCode);
    }


    protected function measureResponseTime(callable $callback): float
    {
        $start = microtime(true);
        $callback();
        return round((microtime(true) - $start) * 1000, 2); // in milliseconds
    }
}
