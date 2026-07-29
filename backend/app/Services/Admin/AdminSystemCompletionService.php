<?php

namespace App\Services\Admin;

use App\Enums\GuardNameEnum;
use App\Models\BulkUploadJob;
use App\Models\CommandRunLog;
use App\Models\Order;
use App\Models\Setting;
use App\Models\SystemAuditLog;
use App\Models\SystemRelease;
use App\Models\User;
use App\Services\AuditService;
use App\Services\BulkUploadService;
use App\Services\SettingService;
use App\Services\SystemOperationsService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AdminSystemCompletionService
{
    public const LIVE_MODULES = [
        'pos-dashboard',
        'roles-users',
        'settings',
        'bulk-uploads',
        'audit-logs',
        'system-operations',
    ];

    public function __construct(
        private readonly AuditService $audit,
        private readonly BulkUploadService $bulk,
        private readonly SettingService $settings,
        private readonly SystemOperationsService $system
    ) {
    }

    public function index(string $module, Request $request): array
    {
        abort_unless(in_array($module, self::LIVE_MODULES, true), 404);

        return match ($module) {
            'pos-dashboard' => $this->posDashboard($request),
            'roles-users' => $this->rolesUsers($request),
            'settings' => $this->settingsIndex($request),
            'bulk-uploads' => $this->bulkUploads($request),
            'audit-logs' => $this->auditLogs($request),
            'system-operations' => $this->systemOperations($request),
        };
    }

    public function show(
        string $module,
        int|string $id,
        Request $request
    ): array {
        abort_unless(in_array($module, self::LIVE_MODULES, true), 404);

        return match ($module) {
            'roles-users' => $this->rolesUsersDetail(
                (int) $id,
                $request->string('view')->toString() ?: 'admins'
            ),
            'settings' => $this->settingDetail((string) $id),
            'bulk-uploads' => $this->bulkUploadDetail((int) $id),
            'audit-logs' => $this->auditDetail((int) $id),
            'system-operations' => $this->systemDetail(
                (int) $id,
                $request->string('view')->toString() ?: 'releases'
            ),
            'pos-dashboard' => $this->posOrderDetail((int) $id),
        };
    }

    public function form(
        string $module,
        Request $request,
        int|string|null $id = null
    ): array {
        $view = $request->string('view')->toString();

        if ($module === 'roles-users') {
            $view = $view ?: 'admins';

            if ($view === 'roles') {
                $role = $id
                    ? Role::query()
                        ->where('guard_name', GuardNameEnum::ADMIN->value)
                        ->with('permissions')
                        ->findOrFail((int) $id)
                    : new Role([
                        'guard_name' => GuardNameEnum::ADMIN->value,
                    ]);

                return [
                    'module' => $module,
                    'view' => $view,
                    'title' => $id ? 'Edit Admin Role' : 'Create Admin Role',
                    'isEdit' => $id !== null,
                    'model' => $role,
                    'permissions' => Permission::query()
                        ->where('guard_name', GuardNameEnum::ADMIN->value)
                        ->orderBy('name')
                        ->get(),
                    'roles' => collect(),
                ];
            }

            $admin = $id
                ? User::query()
                    ->where('access_panel', GuardNameEnum::ADMIN->value)
                    ->with('roles')
                    ->findOrFail((int) $id)
                : new User([
                    'access_panel' => GuardNameEnum::ADMIN->value,
                    'status' => 'active',
                ]);

            return [
                'module' => $module,
                'view' => 'admins',
                'title' => $id ? 'Edit Admin User' : 'Create Admin User',
                'isEdit' => $id !== null,
                'model' => $admin,
                'roles' => Role::query()
                    ->where('guard_name', GuardNameEnum::ADMIN->value)
                    ->orderBy('name')
                    ->get(),
                'permissions' => collect(),
            ];
        }

        if ($module === 'settings') {
            $setting = $id
                ? Setting::query()->where('variable', (string) $id)->firstOrFail()
                : new Setting();

            return [
                'module' => $module,
                'view' => 'default',
                'title' => $id ? 'Edit Setting Group' : 'Create Setting Group',
                'isEdit' => $id !== null,
                'model' => $setting,
                'roles' => collect(),
                'permissions' => collect(),
            ];
        }

        if ($module === 'system-operations') {
            $release = $id
                ? SystemRelease::query()->findOrFail((int) $id)
                : new SystemRelease(['status' => 'planned']);

            return [
                'module' => $module,
                'view' => 'releases',
                'title' => $id ? 'Edit System Release' : 'Create System Release',
                'isEdit' => $id !== null,
                'model' => $release,
                'roles' => collect(),
                'permissions' => collect(),
            ];
        }

        abort(404);
    }

    public function save(
        string $module,
        Request $request,
        User $admin,
        int|string|null $id = null
    ): object {
        return match ($module) {
            'roles-users' => $this->saveRoleOrAdmin(
                $request,
                $admin,
                $id
            ),
            'settings' => $this->saveSetting(
                $request,
                $admin,
                $id
            ),
            'system-operations' => $this->saveRelease(
                $request,
                $admin,
                $id
            ),
            default => throw ValidationException::withMessages([
                'module' => 'This module does not support form saving.',
            ]),
        };
    }

    public function destroy(
        string $module,
        int|string $id,
        Request $request,
        User $admin
    ): void {
        if ($module === 'roles-users') {
            $view = $request->string('view')->toString() ?: 'admins';

            if ($view === 'roles') {
                $role = Role::query()
                    ->where('guard_name', GuardNameEnum::ADMIN->value)
                    ->findOrFail((int) $id);

                if ($role->name === 'Super Admin') {
                    throw ValidationException::withMessages([
                        'role' => 'The Super Admin role cannot be deleted.',
                    ]);
                }

                $before = $role->toArray();
                $role->delete();

                $this->audit->record(
                    $admin,
                    'admin.role.deleted',
                    Role::class,
                    (int) $id,
                    $before,
                    null,
                    $request
                );

                return;
            }

            $user = User::query()
                ->where('access_panel', GuardNameEnum::ADMIN->value)
                ->findOrFail((int) $id);

            if ($user->id === $admin->id) {
                throw ValidationException::withMessages([
                    'admin' => 'You cannot delete your own admin account.',
                ]);
            }

            $before = $user->toArray();
            $user->delete();

            $this->audit->record(
                $admin,
                'admin.user.deleted',
                User::class,
                (int) $id,
                $before,
                null,
                $request
            );

            return;
        }

        if ($module === 'settings') {
            $setting = Setting::query()
                ->where('variable', (string) $id)
                ->firstOrFail();

            $before = $setting->toArray();
            $setting->delete();

            $this->settings->clearSettingCache((string) $id);

            $this->audit->record(
                $admin,
                'admin.setting.deleted',
                Setting::class,
                null,
                $before,
                null,
                $request,
                ['variable' => (string) $id]
            );

            return;
        }

        if ($module === 'system-operations') {
            $release = SystemRelease::query()->findOrFail((int) $id);
            $before = $release->toArray();
            $release->delete();

            $this->audit->record(
                $admin,
                'admin.system_release.deleted',
                SystemRelease::class,
                (int) $id,
                $before,
                null,
                $request
            );

            return;
        }

        throw ValidationException::withMessages([
            'delete' => 'This module cannot be deleted.',
        ]);
    }

    public function action(
        string $module,
        int|string $id,
        Request $request,
        User $admin
    ): array {
        $action = $request->string('action')->toString();

        if ($module === 'bulk-uploads') {
            $job = BulkUploadJob::query()->findOrFail((int) $id);

            if ($action === 'process') {
                $processed = $this->bulk->process($job);

                return [
                    'status' => $processed->status,
                    'processed_rows' => $processed->processed_rows,
                ];
            }

            throw ValidationException::withMessages([
                'action' => 'Unsupported bulk-upload action.',
            ]);
        }

        if ($module === 'system-operations') {
            if ($action === 'deploy-release') {
                $release = SystemRelease::query()->findOrFail((int) $id);

                $fresh = $this->system->recordRelease(
                    $release->version,
                    'deployed',
                    $release->checksum,
                    $release->release_notes,
                    $admin->id
                );

                $this->audit->record(
                    $admin,
                    'admin.system_release.deployed',
                    SystemRelease::class,
                    $fresh->id,
                    $release->toArray(),
                    $fresh->toArray(),
                    $request
                );

                return ['status' => $fresh->status];
            }

            throw ValidationException::withMessages([
                'action' => 'Unsupported system action.',
            ]);
        }

        throw ValidationException::withMessages([
            'action' => 'Unsupported administrative action.',
        ]);
    }

    public function pageAction(
        string $module,
        Request $request,
        User $admin
    ): array {
        $action = $request->string('action')->toString();

        if ($module === 'bulk-uploads' && $action === 'process-pending') {
            return $this->bulk->processPending(
                max(1, min(50, $request->integer('limit', 5)))
            );
        }

        if ($module === 'system-operations') {
            return match ($action) {
                'health-check' => $this->system->health(),
                'readiness-check' => $this->system->readiness(),
                'flag-stuck-orders' => [
                    'flagged' => $this->system->flagStuckOrders(
                        max(15, $request->integer('minutes', 60))
                    ),
                ],
                'clear-cache' => $this->runCommand(
                    'optimize:clear',
                    $admin
                ),
                'queue-restart' => $this->runCommand(
                    'queue:restart',
                    $admin
                ),
                default => throw ValidationException::withMessages([
                    'action' => 'Unsupported system operation.',
                ]),
            };
        }

        throw ValidationException::withMessages([
            'action' => 'Unsupported page operation.',
        ]);
    }

    private function runCommand(string $command, User $admin): array
    {
        return $this->system->runLogged(
            $command,
            'admin:'.$admin->id,
            function () use ($command): array {
                $exit = Artisan::call($command);

                return [
                    'exit_code' => $exit,
                    'output' => Artisan::output(),
                ];
            }
        );
    }

    private function posDashboard(Request $request): array
    {
        $query = Order::query()
            ->whereNotNull('pos_operator_id')
            ->with(['posOperator:id,name,email', 'user:id,name']);

        $this->search($query, $request, [
            'slug',
            'invoice_number',
            'pos_reference',
        ]);
        $this->filter($query, $request, 'status');
        $this->filter($query, $request, 'payment_status');

        $records = $query
            ->latest('id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return $this->payload(
            'pos-dashboard',
            'POS Dashboard',
            'Monitor in-store POS orders, refunds and operators.',
            [
                ['key' => 'order', 'label' => 'Order'],
                ['key' => 'operator', 'label' => 'Operator'],
                ['key' => 'reference', 'label' => 'POS Reference'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'status'],
                ['key' => 'payment', 'label' => 'Payment', 'type' => 'status'],
                ['key' => 'total', 'label' => 'Total', 'type' => 'money'],
                ['key' => 'created', 'label' => 'Created', 'type' => 'date'],
            ],
            $records->through(fn (Order $order) => [
                'id' => $order->id,
                'order' => $order->slug,
                'operator' => $order->posOperator?->name ?? '—',
                'reference' => $order->pos_reference ?: '—',
                'status' => $order->status,
                'payment' => $order->payment_status,
                'total' => (float) $order->final_total,
                'created' => $order->created_at,
            ]),
            [
                $this->selectFilter(
                    'status',
                    'Order status',
                    $this->distinctValues(Order::class, 'status')
                ),
                $this->selectFilter(
                    'payment_status',
                    'Payment status',
                    $this->distinctValues(Order::class, 'payment_status')
                ),
            ],
            [
                'POS orders' => Order::query()
                    ->whereNotNull('pos_operator_id')
                    ->count(),
                'Today' => Order::query()
                    ->whereNotNull('pos_operator_id')
                    ->whereDate('created_at', today())
                    ->count(),
                'Revenue' => (float) Order::query()
                    ->whereNotNull('pos_operator_id')
                    ->whereIn('payment_status', ['paid', 'completed'])
                    ->sum('final_total'),
                'Operators' => User::query()
                    ->where('access_panel', GuardNameEnum::SELLER->value)
                    ->whereHas('roles', fn (Builder $role) =>
                        $role->where('name', 'POS Operator')
                    )
                    ->count(),
            ]
        );
    }

    private function rolesUsers(Request $request): array
    {
        $view = $request->string('view')->toString() ?: 'admins';

        if ($view === 'roles') {
            $query = Role::query()
                ->where('guard_name', GuardNameEnum::ADMIN->value)
                ->withCount(['permissions', 'users']);

            $this->search($query, $request, ['name']);

            $records = $query
                ->orderBy('name')
                ->paginate($this->perPage($request))
                ->withQueryString();

            return $this->payload(
                'roles-users',
                'Admin Roles',
                'Manage admin permissions and role assignments.',
                [
                    ['key' => 'role', 'label' => 'Role'],
                    ['key' => 'permissions', 'label' => 'Permissions'],
                    ['key' => 'users', 'label' => 'Users'],
                    ['key' => 'guard', 'label' => 'Guard'],
                    ['key' => 'created', 'label' => 'Created', 'type' => 'date'],
                ],
                $records->through(fn (Role $role) => [
                    'id' => $role->id,
                    'role' => $role->name,
                    'permissions' => $role->permissions_count,
                    'users' => $role->users_count,
                    'guard' => $role->guard_name,
                    'created' => $role->created_at,
                ]),
                [],
                [
                    'Roles' => Role::query()
                        ->where('guard_name', GuardNameEnum::ADMIN->value)
                        ->count(),
                    'Permissions' => Permission::query()
                        ->where('guard_name', GuardNameEnum::ADMIN->value)
                        ->count(),
                    'Assigned admins' => User::query()
                        ->where('access_panel', GuardNameEnum::ADMIN->value)
                        ->has('roles')
                        ->count(),
                    'Unassigned admins' => User::query()
                        ->where('access_panel', GuardNameEnum::ADMIN->value)
                        ->doesntHave('roles')
                        ->count(),
                ],
                tabs: ['admins' => 'Admin Users', 'roles' => 'Roles'],
                currentTab: $view,
                create: true
            );
        }

        $query = User::query()
            ->where('access_panel', GuardNameEnum::ADMIN->value)
            ->with('roles');

        $this->search($query, $request, ['name', 'email', 'mobile']);
        $this->filter($query, $request, 'status');

        $records = $query
            ->latest('id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return $this->payload(
            'roles-users',
            'Admin Users',
            'Create, activate and assign roles to admin accounts.',
            [
                ['key' => 'admin', 'label' => 'Admin'],
                ['key' => 'email', 'label' => 'Email'],
                ['key' => 'mobile', 'label' => 'Mobile'],
                ['key' => 'roles', 'label' => 'Roles'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'status'],
                ['key' => 'joined', 'label' => 'Joined', 'type' => 'date'],
            ],
            $records->through(fn (User $user) => [
                'id' => $user->id,
                'admin' => $user->name,
                'email' => $user->email,
                'mobile' => $user->mobile ?: '—',
                'roles' => $user->roles->pluck('name')->implode(', ') ?: '—',
                'status' => $user->status,
                'joined' => $user->created_at,
            ]),
            [
                $this->selectFilter(
                    'status',
                    'Status',
                    ['active', 'inactive', 'blocked']
                ),
            ],
            [
                'Admins' => User::query()
                    ->where('access_panel', GuardNameEnum::ADMIN->value)
                    ->count(),
                'Active' => User::query()
                    ->where('access_panel', GuardNameEnum::ADMIN->value)
                    ->where('status', 'active')
                    ->count(),
                'Super Admins' => User::query()
                    ->where('access_panel', GuardNameEnum::ADMIN->value)
                    ->role('Super Admin', GuardNameEnum::ADMIN->value)
                    ->count(),
                'Blocked' => User::query()
                    ->where('access_panel', GuardNameEnum::ADMIN->value)
                    ->where('status', 'blocked')
                    ->count(),
            ],
            tabs: ['admins' => 'Admin Users', 'roles' => 'Roles'],
            currentTab: $view,
            create: true
        );
    }

    private function settingsIndex(Request $request): array
    {
        $query = Setting::query();

        $this->search($query, $request, ['variable']);

        $records = $query
            ->orderBy('variable')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return $this->payload(
            'settings',
            'Settings',
            'Edit versioned JSON setting groups used by FastSheba.',
            [
                ['key' => 'variable', 'label' => 'Variable'],
                ['key' => 'keys', 'label' => 'Keys'],
                ['key' => 'preview', 'label' => 'Preview'],
                ['key' => 'updated', 'label' => 'Updated', 'type' => 'date'],
            ],
            $records->through(fn (Setting $setting) => [
                'id' => $setting->variable,
                'variable' => $setting->variable,
                'keys' => count($setting->value),
                'preview' => Str::limit(
                    json_encode($setting->value, JSON_UNESCAPED_UNICODE),
                    100
                ),
                'updated' => $setting->updated_at,
            ]),
            [],
            [
                'Setting groups' => Setting::query()->count(),
                'Total keys' => Setting::query()
                    ->get()
                    ->sum(fn (Setting $setting) => count($setting->value)),
                'System groups' => Setting::query()
                    ->where('variable', 'like', '%system%')
                    ->count(),
                'Updated today' => Setting::query()
                    ->whereDate('updated_at', today())
                    ->count(),
            ],
            create: true
        );
    }

    private function bulkUploads(Request $request): array
    {
        $query = BulkUploadJob::query()
            ->with(['user:id,name,email', 'seller:id,business_name']);

        $this->search($query, $request, [
            'uuid',
            'original_filename',
            'type',
            'operation',
        ]);
        $this->filter($query, $request, 'status');
        $this->filter($query, $request, 'operation');
        $this->filter($query, $request, 'type');

        $records = $query
            ->latest('id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return $this->payload(
            'bulk-uploads',
            'Bulk Operations',
            'Monitor CSV imports, exports, failures and processing state.',
            [
                ['key' => 'job', 'label' => 'Job'],
                ['key' => 'owner', 'label' => 'Owner'],
                ['key' => 'type', 'label' => 'Type'],
                ['key' => 'operation', 'label' => 'Operation'],
                ['key' => 'progress', 'label' => 'Progress'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'status'],
                ['key' => 'created', 'label' => 'Created', 'type' => 'date'],
            ],
            $records->through(fn (BulkUploadJob $job) => [
                'id' => $job->id,
                'job' => str($job->uuid)->substr(0, 10)->upper()->toString(),
                'owner' => $job->seller?->business_name
                    ?? $job->user?->name
                    ?? 'System',
                'type' => $job->type,
                'operation' => $job->operation,
                'progress' => $job->processed_rows.'/'.$job->total_rows,
                'status' => $job->status,
                'created' => $job->created_at,
            ]),
            [
                $this->selectFilter(
                    'operation',
                    'Operation',
                    ['import', 'export']
                ),
                $this->selectFilter(
                    'status',
                    'Status',
                    $this->distinctValues(BulkUploadJob::class, 'status')
                ),
                $this->selectFilter(
                    'type',
                    'Type',
                    $this->distinctValues(BulkUploadJob::class, 'type')
                ),
            ],
            [
                'Jobs' => BulkUploadJob::query()->count(),
                'Pending' => BulkUploadJob::query()
                    ->where('status', 'pending')
                    ->count(),
                'Completed' => BulkUploadJob::query()
                    ->where('status', 'completed')
                    ->count(),
                'Failed rows' => (int) BulkUploadJob::query()
                    ->sum('failed_rows'),
            ],
            pageActions: [[
                'action' => 'process-pending',
                'label' => 'Process pending jobs',
                'tone' => 'primary',
            ]]
        );
    }

    private function auditLogs(Request $request): array
    {
        $query = SystemAuditLog::query()->with('actor:id,name,email');

        $this->search($query, $request, [
            'action',
            'entity_type',
            'request_id',
            'ip_address',
        ]);
        $this->filter($query, $request, 'actor_role');
        $this->filter($query, $request, 'action');

        $records = $query
            ->latest('created_at')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return $this->payload(
            'audit-logs',
            'Audit Logs',
            'Inspect administrative and system changes with before/after data.',
            [
                ['key' => 'audit', 'label' => 'Audit'],
                ['key' => 'actor', 'label' => 'Actor'],
                ['key' => 'role', 'label' => 'Role'],
                ['key' => 'action', 'label' => 'Action'],
                ['key' => 'entity', 'label' => 'Entity'],
                ['key' => 'ip', 'label' => 'IP'],
                ['key' => 'created', 'label' => 'Created', 'type' => 'date'],
            ],
            $records->through(fn (SystemAuditLog $log) => [
                'id' => $log->id,
                'audit' => '#'.$log->id,
                'actor' => $log->actor?->name ?? 'System',
                'role' => $log->actor_role ?: 'system',
                'action' => $log->action,
                'entity' => class_basename($log->entity_type)
                    .' #'.($log->entity_id ?? '—'),
                'ip' => $log->ip_address ?: '—',
                'created' => $log->created_at,
            ]),
            [
                $this->selectFilter(
                    'actor_role',
                    'Actor role',
                    $this->distinctValues(
                        SystemAuditLog::class,
                        'actor_role'
                    )
                ),
            ],
            [
                'Audit records' => SystemAuditLog::query()->count(),
                'Today' => SystemAuditLog::query()
                    ->whereDate('created_at', today())
                    ->count(),
                'Admin actions' => SystemAuditLog::query()
                    ->where('actor_role', 'admin')
                    ->count(),
                'System actions' => SystemAuditLog::query()
                    ->where('actor_role', 'system')
                    ->count(),
            ]
        );
    }

    private function systemOperations(Request $request): array
    {
        $view = $request->string('view')->toString() ?: 'releases';

        if ($view === 'commands') {
            $query = CommandRunLog::query();

            $this->search($query, $request, [
                'uuid',
                'command',
                'triggered_by',
            ]);
            $this->filter($query, $request, 'status');

            $records = $query
                ->latest('id')
                ->paginate($this->perPage($request))
                ->withQueryString();

            return $this->payload(
                'system-operations',
                'Command Runs',
                'Inspect admin-triggered maintenance command results.',
                [
                    ['key' => 'run', 'label' => 'Run'],
                    ['key' => 'command', 'label' => 'Command'],
                    ['key' => 'triggered', 'label' => 'Triggered by'],
                    ['key' => 'duration', 'label' => 'Duration'],
                    ['key' => 'status', 'label' => 'Status', 'type' => 'status'],
                    ['key' => 'started', 'label' => 'Started', 'type' => 'date'],
                ],
                $records->through(fn (CommandRunLog $log) => [
                    'id' => $log->id,
                    'run' => str($log->uuid)->substr(0, 10)->upper()->toString(),
                    'command' => $log->command,
                    'triggered' => $log->triggered_by,
                    'duration' => $log->duration_ms !== null
                        ? $log->duration_ms.' ms'
                        : '—',
                    'status' => $log->status,
                    'started' => $log->started_at,
                ]),
                [
                    $this->selectFilter(
                        'status',
                        'Status',
                        ['running', 'success', 'failed']
                    ),
                ],
                [
                    'Runs' => CommandRunLog::query()->count(),
                    'Successful' => CommandRunLog::query()
                        ->where('status', 'success')
                        ->count(),
                    'Failed' => CommandRunLog::query()
                        ->where('status', 'failed')
                        ->count(),
                    'Average duration' => round(
                        (float) CommandRunLog::query()->avg('duration_ms')
                    ),
                ],
                tabs: [
                    'releases' => 'System Releases',
                    'commands' => 'Command Runs',
                    'health' => 'Health & Maintenance',
                ],
                currentTab: $view
            );
        }

        if ($view === 'health') {
            $health = $this->system->health();
            $readiness = $this->system->readiness();

            return [
                'module' => 'system-operations',
                'title' => 'Health & Maintenance',
                'description' =>
                    'Run safe operational checks and maintenance commands.',
                'healthMode' => true,
                'health' => $health,
                'readiness' => $readiness,
                'tabs' => [
                    'releases' => 'System Releases',
                    'commands' => 'Command Runs',
                    'health' => 'Health & Maintenance',
                ],
                'currentTab' => $view,
            ];
        }

        $query = SystemRelease::query();

        $this->search($query, $request, ['version', 'status']);
        $this->filter($query, $request, 'status');

        $records = $query
            ->latest('id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return $this->payload(
            'system-operations',
            'System Releases',
            'Record planned, staged and deployed FastSheba releases.',
            [
                ['key' => 'version', 'label' => 'Version'],
                ['key' => 'checksum', 'label' => 'Checksum'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'status'],
                ['key' => 'deployed_by', 'label' => 'Deployed by'],
                ['key' => 'deployed', 'label' => 'Deployed', 'type' => 'date'],
                ['key' => 'created', 'label' => 'Created', 'type' => 'date'],
            ],
            $records->through(fn (SystemRelease $release) => [
                'id' => $release->id,
                'version' => $release->version,
                'checksum' => $release->checksum
                    ? Str::limit($release->checksum, 24)
                    : '—',
                'status' => $release->status,
                'deployed_by' => $release->deployed_by ?: '—',
                'deployed' => $release->deployed_at,
                'created' => $release->created_at,
            ]),
            [
                $this->selectFilter(
                    'status',
                    'Status',
                    ['planned', 'staged', 'deployed', 'failed', 'rolled_back']
                ),
            ],
            [
                'Releases' => SystemRelease::query()->count(),
                'Deployed' => SystemRelease::query()
                    ->where('status', 'deployed')
                    ->count(),
                'Planned' => SystemRelease::query()
                    ->where('status', 'planned')
                    ->count(),
                'Failed' => SystemRelease::query()
                    ->where('status', 'failed')
                    ->count(),
            ],
            tabs: [
                'releases' => 'System Releases',
                'commands' => 'Command Runs',
                'health' => 'Health & Maintenance',
            ],
            currentTab: $view,
            create: true
        );
    }

    private function rolesUsersDetail(int $id, string $view): array
    {
        if ($view === 'roles') {
            $role = Role::query()
                ->where('guard_name', GuardNameEnum::ADMIN->value)
                ->with(['permissions', 'users'])
                ->findOrFail($id);

            return $this->detail(
                'roles-users',
                'Admin Role',
                $role->name,
                $role->id,
                [
                    $this->section('Role', [
                        $this->item('Name', $role->name),
                        $this->item('Guard', $role->guard_name),
                        $this->item(
                            'Permissions',
                            $role->permissions->count()
                        ),
                        $this->item('Users', $role->users->count()),
                    ]),
                ],
                tables: [[
                    'title' => 'Permissions',
                    'columns' => ['Permission'],
                    'rows' => $role->permissions
                        ->map(fn (Permission $permission) => [
                            $permission->name,
                        ])->all(),
                ]],
                edit: true,
                delete: $role->name !== 'Super Admin',
                query: ['view' => 'roles']
            );
        }

        $user = User::query()
            ->where('access_panel', GuardNameEnum::ADMIN->value)
            ->with('roles.permissions')
            ->findOrFail($id);

        return $this->detail(
            'roles-users',
            'Admin User',
            $user->name,
            $user->id,
            [
                $this->section('Admin account', [
                    $this->item('Name', $user->name),
                    $this->item('Email', $user->email),
                    $this->item('Mobile', $user->mobile ?: '—'),
                    $this->item('Status', $user->status, 'status'),
                    $this->item(
                        'Roles',
                        $user->roles->pluck('name')->implode(', ') ?: '—'
                    ),
                    $this->item(
                        'Email verified',
                        $user->email_verified_at ? 'Yes' : 'No'
                    ),
                    $this->item('Joined', $user->created_at, 'date'),
                ]),
            ],
            edit: true,
            delete: true,
            query: ['view' => 'admins']
        );
    }

    private function settingDetail(string $variable): array
    {
        $setting = Setting::query()
            ->where('variable', $variable)
            ->firstOrFail();

        return $this->detail(
            'settings',
            'Setting Group',
            $setting->variable,
            $setting->variable,
            [
                $this->section('Setting', [
                    $this->item('Variable', $setting->variable),
                    $this->item('Keys', count($setting->value)),
                    $this->item(
                        'JSON',
                        json_encode(
                            $setting->value,
                            JSON_PRETTY_PRINT
                            | JSON_UNESCAPED_UNICODE
                            | JSON_UNESCAPED_SLASHES
                        )
                    ),
                    $this->item('Updated', $setting->updated_at, 'date'),
                ]),
            ],
            edit: true,
            delete: true
        );
    }

    private function bulkUploadDetail(int $id): array
    {
        $job = BulkUploadJob::query()
            ->with(['user', 'seller'])
            ->findOrFail($id);

        $actions = $job->status === 'pending'
            ? [[
                'action' => 'process',
                'label' => 'Process job now',
                'tone' => 'primary',
                'confirm' => true,
                'fields' => [],
            ]]
            : [];

        return $this->detail(
            'bulk-uploads',
            'Bulk Job',
            $job->uuid,
            $job->id,
            [
                $this->section('Job', [
                    $this->item(
                        'Owner',
                        $job->seller?->business_name
                            ?? $job->user?->name
                            ?? 'System'
                    ),
                    $this->item('Operation', $job->operation),
                    $this->item('Type', $job->type),
                    $this->item('Status', $job->status, 'status'),
                    $this->item(
                        'Progress',
                        $job->processed_rows.'/'.$job->total_rows
                    ),
                    $this->item('Successful rows', $job->successful_rows),
                    $this->item('Failed rows', $job->failed_rows),
                    $this->item('Original file', $job->original_filename ?: '—'),
                    $this->item('Stored path', $job->stored_path ?: '—'),
                    $this->item('Failed rows path', $job->failed_rows_path ?: '—'),
                    $this->item('Started', $job->started_at, 'date'),
                    $this->item('Finished', $job->finished_at, 'date'),
                ]),
            ],
            actions: $actions
        );
    }

    private function auditDetail(int $id): array
    {
        $log = SystemAuditLog::query()
            ->with('actor')
            ->findOrFail($id);

        return $this->detail(
            'audit-logs',
            'Audit Record',
            '#'.$log->id,
            $log->id,
            [
                $this->section('Audit', [
                    $this->item('Actor', $log->actor?->name ?? 'System'),
                    $this->item('Actor role', $log->actor_role ?: 'system'),
                    $this->item('Action', $log->action),
                    $this->item('Entity type', $log->entity_type),
                    $this->item('Entity ID', $log->entity_id ?: '—'),
                    $this->item('IP address', $log->ip_address ?: '—'),
                    $this->item('Request ID', $log->request_id ?: '—'),
                    $this->item('Created', $log->created_at, 'date'),
                ]),
                $this->section('Change data', [
                    $this->item(
                        'Before',
                        json_encode(
                            $log->before_data,
                            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
                        ) ?: '—'
                    ),
                    $this->item(
                        'After',
                        json_encode(
                            $log->after_data,
                            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
                        ) ?: '—'
                    ),
                    $this->item(
                        'Metadata',
                        json_encode(
                            $log->metadata,
                            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
                        ) ?: '—'
                    ),
                ]),
            ]
        );
    }

    private function systemDetail(int $id, string $view): array
    {
        if ($view === 'commands') {
            $log = CommandRunLog::query()->findOrFail($id);

            return $this->detail(
                'system-operations',
                'Command Run',
                $log->command,
                $log->id,
                [
                    $this->section('Command', [
                        $this->item('UUID', $log->uuid),
                        $this->item('Command', $log->command),
                        $this->item('Status', $log->status, 'status'),
                        $this->item('Triggered by', $log->triggered_by),
                        $this->item('Duration', $log->duration_ms !== null
                            ? $log->duration_ms.' ms'
                            : '—'),
                        $this->item('Started', $log->started_at, 'date'),
                        $this->item('Finished', $log->finished_at, 'date'),
                        $this->item('Output', $log->output ?: '—'),
                        $this->item('Error', $log->error ?: '—'),
                    ]),
                ],
                query: ['view' => 'commands']
            );
        }

        $release = SystemRelease::query()->findOrFail($id);

        $actions = $release->status !== 'deployed'
            ? [[
                'action' => 'deploy-release',
                'label' => 'Mark as deployed',
                'tone' => 'success',
                'confirm' => true,
                'fields' => [],
            ]]
            : [];

        return $this->detail(
            'system-operations',
            'System Release',
            $release->version,
            $release->id,
            [
                $this->section('Release', [
                    $this->item('Version', $release->version),
                    $this->item('Status', $release->status, 'status'),
                    $this->item('Checksum', $release->checksum ?: '—'),
                    $this->item('Release notes', $release->release_notes ?: '—'),
                    $this->item('Deployed by', $release->deployed_by ?: '—'),
                    $this->item('Deployed', $release->deployed_at, 'date'),
                    $this->item('Created', $release->created_at, 'date'),
                ]),
            ],
            actions: $actions,
            edit: true,
            delete: true,
            query: ['view' => 'releases']
        );
    }

    private function posOrderDetail(int $id): array
    {
        $order = Order::query()
            ->whereNotNull('pos_operator_id')
            ->with(['posOperator', 'items.product', 'posRefunds'])
            ->findOrFail($id);

        return $this->detail(
            'pos-dashboard',
            'POS Order',
            $order->slug,
            $order->id,
            [
                $this->section('POS order', [
                    $this->item('Reference', $order->pos_reference ?: '—'),
                    $this->item('Operator', $order->posOperator?->name ?? '—'),
                    $this->item('Status', $order->status, 'status'),
                    $this->item('Payment', $order->payment_status, 'status'),
                    $this->item('Total', (float) $order->final_total, 'money'),
                    $this->item('Refunds', $order->posRefunds->count()),
                    $this->item('Created', $order->created_at, 'date'),
                ]),
            ],
            tables: [[
                'title' => 'Items',
                'columns' => ['Product', 'Quantity', 'Subtotal', 'Status'],
                'rows' => $order->items->map(fn ($item) => [
                    $item->product_title ?: $item->product?->title ?: '—',
                    $item->quantity,
                    '৳'.number_format((float) $item->subtotal, 2),
                    str($item->status)->replace('_', ' ')->title()->toString(),
                ])->all(),
            ]]
        );
    }

    private function saveRoleOrAdmin(
        Request $request,
        User $actor,
        int|string|null $id
    ): object {
        $view = $request->string('view')->toString() ?: 'admins';

        if ($view === 'roles') {
            $role = $id
                ? Role::query()
                    ->where('guard_name', GuardNameEnum::ADMIN->value)
                    ->findOrFail((int) $id)
                : new Role();

            $data = $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('roles', 'name')
                        ->where('guard_name', GuardNameEnum::ADMIN->value)
                        ->ignore($role->id),
                ],
                'permission_ids' => ['nullable', 'array'],
                'permission_ids.*' => [
                    'integer',
                    Rule::exists('permissions', 'id')
                        ->where('guard_name', GuardNameEnum::ADMIN->value),
                ],
            ]);

            if ($role->exists && $role->name === 'Super Admin') {
                $data['name'] = 'Super Admin';
            }

            $before = $role->exists ? $role->toArray() : null;
            $role->name = $data['name'];
            $role->guard_name = GuardNameEnum::ADMIN->value;
            $role->save();

            $permissions = Permission::query()
                ->where('guard_name', GuardNameEnum::ADMIN->value)
                ->whereIn('id', $data['permission_ids'] ?? [])
                ->get();

            $role->syncPermissions($permissions);

            $this->audit->record(
                $actor,
                'admin.role.saved',
                Role::class,
                $role->id,
                $before,
                $role->fresh('permissions')->toArray(),
                $request
            );

            return $role;
        }

        $user = $id
            ? User::query()
                ->where('access_panel', GuardNameEnum::ADMIN->value)
                ->findOrFail((int) $id)
            : new User();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'mobile' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique('users', 'mobile')->ignore($user->id),
            ],
            'status' => [
                'required',
                Rule::in(['active', 'inactive', 'blocked']),
            ],
            'password' => [
                $user->exists ? 'nullable' : 'required',
                'string',
                'min:8',
                'confirmed',
            ],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => [
                'integer',
                Rule::exists('roles', 'id')
                    ->where('guard_name', GuardNameEnum::ADMIN->value),
            ],
        ]);

        $before = $user->exists ? $user->toArray() : null;

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'mobile' => $data['mobile'] ?? null,
            'status' => $data['status'],
            'access_panel' => GuardNameEnum::ADMIN->value,
            'email_verified_at' => $user->email_verified_at ?: now(),
        ]);

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->save();

        $roles = Role::query()
            ->where('guard_name', GuardNameEnum::ADMIN->value)
            ->whereIn('id', $data['role_ids'] ?? [])
            ->get();

        $user->syncRoles($roles);

        $this->audit->record(
            $actor,
            'admin.user.saved',
            User::class,
            $user->id,
            $before,
            $user->fresh('roles')->toArray(),
            $request
        );

        return $user;
    }

    private function saveSetting(
        Request $request,
        User $admin,
        int|string|null $id
    ): Setting {
        $setting = $id
            ? Setting::query()
                ->where('variable', (string) $id)
                ->firstOrFail()
            : new Setting();

        $data = $request->validate([
            'variable' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9_\-]+$/',
                Rule::unique('settings', 'variable')
                    ->ignore($setting->variable, 'variable'),
            ],
            'value_json' => ['required', 'json'],
        ]);

        $decoded = json_decode(
            $data['value_json'],
            true,
            flags: JSON_THROW_ON_ERROR
        );

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                'value_json' => 'The JSON value must decode to an object or array.',
            ]);
        }

        $oldVariable = $setting->exists ? $setting->variable : null;
        $before = $setting->exists ? $setting->toArray() : null;

        if ($setting->exists && $oldVariable !== $data['variable']) {
            $setting->delete();
            $setting = new Setting();
        }

        $setting->variable = $data['variable'];
        $setting->value = $decoded;
        $setting->save();

        if ($oldVariable) {
            $this->settings->clearSettingCache($oldVariable);
        }
        $this->settings->clearSettingCache($setting->variable);

        $this->audit->record(
            $admin,
            'admin.setting.saved',
            Setting::class,
            null,
            $before,
            $setting->fresh()->toArray(),
            $request,
            ['variable' => $setting->variable]
        );

        return $setting;
    }

    private function saveRelease(
        Request $request,
        User $admin,
        int|string|null $id
    ): SystemRelease {
        $release = $id
            ? SystemRelease::query()->findOrFail((int) $id)
            : new SystemRelease();

        $data = $request->validate([
            'version' => [
                'required',
                'string',
                'max:100',
                Rule::unique('system_releases', 'version')
                    ->ignore($release->id),
            ],
            'status' => [
                'required',
                Rule::in([
                    'planned',
                    'staged',
                    'deployed',
                    'failed',
                    'rolled_back',
                ]),
            ],
            'checksum' => ['nullable', 'string', 'max:255'],
            'release_notes' => ['nullable', 'string', 'max:10000'],
        ]);

        $before = $release->exists ? $release->toArray() : null;

        $fresh = $this->system->recordRelease(
            $data['version'],
            $data['status'],
            $data['checksum'] ?? null,
            $data['release_notes'] ?? null,
            $admin->id
        );

        $this->audit->record(
            $admin,
            'admin.system_release.saved',
            SystemRelease::class,
            $fresh->id,
            $before,
            $fresh->toArray(),
            $request
        );

        return $fresh;
    }

    private function payload(
        string $module,
        string $title,
        string $description,
        array $columns,
        LengthAwarePaginator $records,
        array $filters,
        array $stats,
        array $tabs = [],
        ?string $currentTab = null,
        bool $create = false,
        array $pageActions = []
    ): array {
        return compact(
            'module',
            'title',
            'description',
            'columns',
            'records',
            'filters',
            'stats',
            'tabs',
            'currentTab',
            'create',
            'pageActions'
        ) + [
            'healthMode' => false,
            'routeGroup' => 'system',
            'view' => $currentTab ?: 'default',
            'canCreate' => $create,
            'canSync' => false,
        ];
    }

    private function detail(
        string $module,
        string $title,
        string $subtitle,
        int|string $recordId,
        array $sections,
        array $tables = [],
        array $actions = [],
        bool $edit = false,
        bool $delete = false,
        array $query = []
    ): array {
        return compact(
            'module',
            'title',
            'subtitle',
            'recordId',
            'sections',
            'tables',
            'actions',
            'edit',
            'delete',
            'query'
        ) + [
            'routeGroup' => 'system',
            'editable' => $edit,
            'deletable' => $delete,
        ];
    }

    private function section(string $title, array $items): array
    {
        return compact('title', 'items');
    }

    private function item(
        string $label,
        mixed $value,
        string $type = 'text'
    ): array {
        return compact('label', 'value', 'type');
    }

    private function search(
        Builder $query,
        Request $request,
        array $columns
    ): void {
        if (! $request->filled('search')) {
            return;
        }

        $search = $request->string('search')->toString();

        $query->where(function (Builder $builder) use (
            $columns,
            $search
        ): void {
            foreach ($columns as $index => $column) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $builder->{$method}(
                    $column,
                    'like',
                    '%'.$search.'%'
                );
            }
        });
    }

    private function filter(
        Builder $query,
        Request $request,
        string $column,
        ?string $requestKey = null
    ): void {
        $key = $requestKey ?: $column;

        if ($request->filled($key)) {
            $query->where($column, $request->input($key));
        }
    }

    private function perPage(Request $request): int
    {
        return min(100, max(10, $request->integer('per_page', 20)));
    }

    private function selectFilter(
        string $key,
        string $label,
        array $options
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'type' => 'select',
            'options' => array_values(array_filter($options)),
        ];
    }

    private function distinctValues(
        string $model,
        string $column
    ): array {
        return $model::query()
            ->whereNotNull($column)
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->filter()
            ->values()
            ->all();
    }
}
