<?php

namespace App\Services;

use App\Models\SystemAuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuditService
{
    public function record(
        ?User $actor,
        string $action,
        string $entityType,
        ?int $entityId,
        ?array $before = null,
        ?array $after = null,
        ?Request $request = null,
        array $metadata = []
    ): SystemAuditLog {
        $request ??= request();

        return SystemAuditLog::query()->create([
            'actor_id' => $actor?->id,
            'actor_role' => $this->actorRole($actor),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'before_data' => $before,
            'after_data' => $after,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'request_id' => (string) Str::uuid(),
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    private function actorRole(?User $actor): ?string
    {
        if (! $actor) {
            return 'system';
        }

        $panel = $actor->access_panel;

        if ($panel instanceof \BackedEnum) {
            return (string) $panel->value;
        }

        return $panel ? (string) $panel : 'web';
    }
}
