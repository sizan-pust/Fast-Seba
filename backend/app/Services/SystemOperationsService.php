<?php

namespace App\Services;

use App\Models\CommandRunLog;
use App\Models\Order;
use App\Models\SystemRelease;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class SystemOperationsService
{
    public function health(): array
    {
        $database = false;
        $cache = false;
        $storage = false;
        $queue = false;

        try {
            DB::select('select 1');
            $database = true;
        } catch (Throwable) {
        }

        try {
            $key = 'fastsheba-health-'.Str::uuid();
            Cache::put($key, 'ok', 10);
            $cache = Cache::get($key) === 'ok';
            Cache::forget($key);
        } catch (Throwable) {
        }

        try {
            $path = 'health/'.Str::uuid().'.txt';
            Storage::put($path, 'ok');
            $storage = Storage::get($path) === 'ok';
            Storage::delete($path);
        } catch (Throwable) {
        }

        try {
            $queue = DB::table('jobs')->count() >= 0;
        } catch (Throwable) {
        }

        $healthy = $database && $cache && $storage;

        return [
            'status' => $healthy ? 'healthy' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'application' => [
                'name' => config('app.name'),
                'environment' => app()->environment(),
                'debug' => (bool) config('app.debug'),
                'version' => config('fastsheba.version', 'dev'),
            ],
            'checks' => [
                'database' => $database,
                'cache' => $cache,
                'storage' => $storage,
                'queue_table' => $queue,
            ],
        ];
    }

    public function readiness(): array
    {
        $health = $this->health();
        $pendingMigrations = [];

        try {
            $files = collect(
                glob(database_path('migrations/*.php'))
            )->map(
                fn ($path) => pathinfo($path, PATHINFO_FILENAME)
            );

            $ran = DB::table('migrations')
                ->pluck('migration');

            $pendingMigrations = $files
                ->diff($ran)
                ->values()
                ->all();
        } catch (Throwable) {
            $pendingMigrations = ['migration-check-failed'];
        }

        return array_merge($health, [
            'ready' =>
                $health['status'] === 'healthy'
                && $pendingMigrations === [],
            'pending_migrations' => $pendingMigrations,
        ]);
    }

    public function runLogged(
        string $command,
        string $triggeredBy,
        Closure $callback
    ): mixed {
        $started = hrtime(true);

        $log = CommandRunLog::query()->create([
            'command' => $command,
            'status' => 'running',
            'triggered_by' => $triggeredBy,
            'started_at' => now(),
        ]);

        try {
            $result = $callback();

            $duration = (int) round(
                (hrtime(true) - $started) / 1_000_000
            );

            $log->update([
                'status' => 'success',
                'finished_at' => now(),
                'duration_ms' => $duration,
                'output' => is_scalar($result)
                    ? (string) $result
                    : json_encode($result),
            ]);

            return $result;
        } catch (Throwable $exception) {
            $duration = (int) round(
                (hrtime(true) - $started) / 1_000_000
            );

            $log->update([
                'status' => 'failed',
                'finished_at' => now(),
                'duration_ms' => $duration,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function flagStuckOrders(int $minutes = 60): int
    {
        $threshold = now()->subMinutes($minutes);

        $orders = Order::query()
            ->whereIn('status', [
                'awaiting_store_response',
                'accepted',
                'preparing',
                'ready_for_pickup',
                'picked_up',
                'out_for_delivery',
            ])
            ->where('updated_at', '<=', $threshold)
            ->get();

        foreach ($orders as $order) {
            $metadata = $order->metadata ?? [];
            $metadata['stuck_order'] = [
                'flagged_at' => now()->toIso8601String(),
                'status' => $order->status,
                'threshold_minutes' => $minutes,
            ];

            $order->update(['metadata' => $metadata]);
        }

        return $orders->count();
    }

    public function openApiDocument(): array
    {
        $paths = [];

        foreach (Route::getRoutes() as $route) {
            $uri = '/'.$route->uri();

            if (! str_starts_with($uri, '/api/')) {
                continue;
            }

            $openApiPath = preg_replace(
                '/\{([^}]+)\??\}/',
                '{$1}',
                $uri
            );

            foreach ($route->methods() as $method) {
                if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
                    continue;
                }

                $paths[$openApiPath][strtolower($method)] = [
                    'summary' => $route->getName()
                        ?: Str::headline(
                            str_replace(
                                ['api/', '/', '-'],
                                ['', ' ', ' '],
                                $route->uri()
                            )
                        ),
                    'operationId' => $route->getName()
                        ?: Str::camel(
                            $method.'_'.$route->uri()
                        ),
                    'tags' => [
                        Str::headline(
                            explode('/', $route->uri())[1] ?? 'API'
                        ),
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Successful response',
                        ],
                        '422' => [
                            'description' => 'Validation error',
                        ],
                    ],
                ];
            }
        }

        ksort($paths);

        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'FastSheba API',
                'version' => config('fastsheba.version', '1.0.0'),
                'description' =>
                    'FastSheba customer, seller, rider, POS, pharmacy and admin API.',
            ],
            'servers' => [
                ['url' => rtrim(config('app.url'), '/')],
            ],
            'components' => [
                'securitySchemes' => [
                    'sanctum' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'Token',
                    ],
                ],
            ],
            'paths' => $paths,
        ];
    }

    public function recordRelease(
        string $version,
        string $status,
        ?string $checksum,
        ?string $notes,
        ?int $userId
    ): SystemRelease {
        return SystemRelease::query()->updateOrCreate(
            ['version' => $version],
            [
                'status' => $status,
                'checksum' => $checksum,
                'release_notes' => $notes,
                'deployed_by' =>
                    $status === 'deployed' ? $userId : null,
                'deployed_at' =>
                    $status === 'deployed' ? now() : null,
            ]
        );
    }
}
