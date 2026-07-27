param(
    [string]$BackendPath = "D:\Workspace\fastsheba-platform\backend"
)

$ErrorActionPreference = "Stop"

function Get-OneFile([string]$Pattern) {
    $file = Get-ChildItem -Path "$BackendPath\database\migrations" -Filter $Pattern |
        Sort-Object Name |
        Select-Object -First 1

    if (-not $file) {
        throw "Migration not found: $Pattern"
    }

    return $file.FullName
}

function Write-Utf8NoBom([string]$Path, [string]$Content) {
    $directory = Split-Path $Path -Parent
    if ($directory) {
        New-Item -ItemType Directory -Force -Path $directory | Out-Null
    }

    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($Path, $Content, $utf8NoBom)
}

function Backup-File([string]$Path, [string]$BackupRoot) {
    if (Test-Path $Path) {
        $relative = $Path.Substring($BackendPath.Length).TrimStart("\")
        $destination = Join-Path $BackupRoot $relative
        $directory = Split-Path $destination -Parent
        New-Item -ItemType Directory -Force -Path $directory | Out-Null
        Copy-Item $Path $destination -Force
    }
}

if (-not (Test-Path "$BackendPath\artisan")) {
    throw "Laravel backend not found at: $BackendPath"
}

$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$backupRoot = Join-Path $BackendPath "_phase1b_backups\$timestamp"
New-Item -ItemType Directory -Force -Path $backupRoot | Out-Null

$files = @{
    Users     = Get-OneFile "*add_fastsheba_fields_to_users_table.php"
    Countries = Get-OneFile "*create_countries_table.php"
    Settings  = Get-OneFile "*create_settings_table.php"
    Zones     = Get-OneFile "*create_delivery_zones_table.php"
    Wallets   = Get-OneFile "*create_wallets_table.php"
}

$pathsToBackup = @(
    $files.Values
    "$BackendPath\app\Models\User.php"
    "$BackendPath\app\Models\Country.php"
    "$BackendPath\app\Models\Setting.php"
    "$BackendPath\app\Models\DeliveryZone.php"
    "$BackendPath\app\Models\Wallet.php"
    "$BackendPath\database\seeders\FoundationSeeder.php"
    "$BackendPath\app\Enums\GuardNameEnum.php"
    "$BackendPath\app\Enums\DefaultSystemRolesEnum.php"
    "$BackendPath\app\Enums\UserLoginTypeEnum.php"
    "$BackendPath\app\Enums\WalletTypeEnum.php"
    "$BackendPath\app\Enums\DeviceTypeEnum.php"
    "$BackendPath\app\Enums\NotificationRoleTypeEnum.php"
    "$BackendPath\app\Models\UserFcmToken.php"
    "$BackendPath\database\migrations\2026_07_28_000001_create_user_fcm_tokens_table.php"
)

foreach ($path in $pathsToBackup) {
    Backup-File $path $backupRoot
}

Write-Utf8NoBom -Path $files.Users -Content @'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();

            $table->string('firebase_uid')->nullable()->unique()->after('id');
            $table->string('mobile', 20)->nullable()->unique()->after('firebase_uid');
            $table->string('country_code', 10)->nullable()->after('mobile');

            $table->string('referral_code', 32)->nullable()->unique()->after('country_code');
            $table->string('friends_code', 32)->nullable()->after('referral_code');
            $table->timestamp('referral_prompt_dismissed_at')->nullable()->after('friends_code');
            $table->decimal('reward_points', 10, 2)->default(0);

            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->enum('access_panel', ['web', 'admin', 'seller'])
                ->default('web')
                ->index()
                ->comment('Defines the access panel for the user: web, admin, or seller');

            $table->string('country')->nullable();
            $table->string('iso_2', 2)->nullable();

            $table->timestamp('mobile_verified_at')->nullable();
            $table->enum('logged_in_type', ['google', 'apple', 'platform'])->nullable();

            $table->softDeletes();

            $table->index(['status', 'access_panel']);
            $table->index('friends_code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['status', 'access_panel']);
            $table->dropIndex(['friends_code']);

            $table->dropColumn([
                'firebase_uid',
                'mobile',
                'country_code',
                'referral_code',
                'friends_code',
                'referral_prompt_dismissed_at',
                'reward_points',
                'status',
                'access_panel',
                'country',
                'iso_2',
                'mobile_verified_at',
                'logged_in_type',
                'deleted_at',
            ]);

            $table->string('email')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
        });
    }
};
'@

Write-Utf8NoBom -Path $files.Countries -Content @'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->char('iso3', 3)->unique();
            $table->char('iso2', 2)->unique();
            $table->char('numeric_code', 3)->nullable()->unique();
            $table->string('phonecode', 10);
            $table->string('capital')->nullable();
            $table->char('currency', 3);
            $table->string('currency_name')->nullable();
            $table->string('currency_symbol', 10)->nullable();
            $table->string('tld', 20)->nullable();
            $table->string('native')->nullable();
            $table->string('region')->nullable();
            $table->string('subregion')->nullable();
            $table->json('timezones')->nullable();
            $table->json('translations')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('emoji', 32)->nullable();
            $table->string('emojiU')->nullable();
            $table->boolean('flag')->default(true);
            $table->string('wikiDataId', 32)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
'@

Write-Utf8NoBom -Path $files.Settings -Content @'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('variable')->primary();
            $table->text('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
'@

Write-Utf8NoBom -Path $files.Zones -Content @'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('slug', 255)->unique();

            $table->decimal('center_latitude', 10, 8);
            $table->decimal('center_longitude', 11, 8);
            $table->double('radius_km');
            $table->integer('rush_delivery_time_per_km')->nullable();
            $table->integer('rush_delivery_charges')->nullable();
            $table->integer('delivery_time_per_km');
            $table->integer('regular_delivery_charges');
            $table->integer('free_delivery_amount')->nullable();
            $table->integer('distance_based_delivery_charges')->nullable();
            $table->integer('per_store_drop_off_fee')->nullable();
            $table->integer('handling_charges')->nullable();
            $table->integer('buffer_time');

            $table->json('boundary_json')->nullable();
            $table->boolean('rush_delivery_enabled')->default(false);

            $table->decimal('delivery_boy_base_fee', 10, 2)->nullable();
            $table->decimal('delivery_boy_per_store_pickup_fee', 10, 2)->nullable();
            $table->decimal('delivery_boy_distance_based_fee', 10, 2)->nullable();
            $table->decimal('delivery_boy_per_order_incentive', 10, 2)->nullable();

            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_zones');
    }
};
'@

Write-Utf8NoBom -Path $files.Wallets -Content @'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32)->default('customer');
            $table->decimal('balance', 10, 2)->default(0);
            $table->decimal('blocked_balance', 15, 2)->default(0);
            $table->string('currency_code', 3)->default('BDT');
            $table->timestamps();

            $table->unique(['user_id', 'type'], 'wallets_user_id_type_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
'@

Write-Utf8NoBom -Path "$BackendPath\database\migrations\2026_07_28_000001_create_user_fcm_tokens_table.php" -Content @'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_fcm_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('fcm_token')->unique();
            $table->enum('device_type', ['android', 'ios', 'web'])->nullable();
            $table->enum('role_type', ['admin', 'customer', 'seller', 'rider'])->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_fcm_tokens');
    }
};
'@

Write-Utf8NoBom -Path "$BackendPath\app\Enums\GuardNameEnum.php" -Content @'
<?php

namespace App\Enums;

enum GuardNameEnum: string
{
    case ADMIN = 'admin';
    case SELLER = 'seller';
    case WEB = 'web';

    public static function fromString(?string $guardName): self
    {
        return match ($guardName) {
            'admin' => self::ADMIN,
            'seller' => self::SELLER,
            default => self::WEB,
        };
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Enums\DefaultSystemRolesEnum.php" -Content @'
<?php

namespace App\Enums;

enum DefaultSystemRolesEnum: string
{
    case SUPER_ADMIN = 'Super Admin';
    case SELLER = 'seller';
    case CUSTOMER = 'customer';
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Enums\UserLoginTypeEnum.php" -Content @'
<?php

namespace App\Enums;

enum UserLoginTypeEnum: string
{
    case GOOGLE = 'google';
    case APPLE = 'apple';
    case PLATFORM = 'platform';
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Enums\WalletTypeEnum.php" -Content @'
<?php

namespace App\Enums;

enum WalletTypeEnum: string
{
    case CUSTOMER = 'customer';
    case SELLER = 'seller';
    case DELIVERY_BOY = 'delivery_boy';
    case SELLER_AD = 'seller_ad';
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Enums\DeviceTypeEnum.php" -Content @'
<?php

namespace App\Enums;

enum DeviceTypeEnum: string
{
    case ANDROID = 'android';
    case IOS = 'ios';
    case WEB = 'web';
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Enums\NotificationRoleTypeEnum.php" -Content @'
<?php

namespace App\Enums;

enum NotificationRoleTypeEnum: string
{
    case ADMIN = 'admin';
    case CUSTOMER = 'customer';
    case SELLER = 'seller';
    case RIDER = 'rider';
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Models\User.php" -Content @'
<?php

namespace App\Models;

use App\Enums\GuardNameEnum;
use App\Enums\UserLoginTypeEnum;
use App\Enums\WalletTypeEnum;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements HasMedia, MustVerifyEmail
{
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use InteractsWithMedia;
    use MustVerifyEmailTrait;
    use Notifiable;
    use SoftDeletes;

    protected $appends = ['profile_image'];

    protected $fillable = [
        'name',
        'email',
        'mobile',
        'country_code',
        'referral_code',
        'friends_code',
        'referral_prompt_dismissed_at',
        'reward_points',
        'remember_token',
        'status',
        'password',
        'access_panel',
        'iso_2',
        'country',
        'firebase_uid',
        'email_verified_at',
        'mobile_verified_at',
        'logged_in_type',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'reward_points' => 'decimal:2',
            'email_verified_at' => 'datetime',
            'mobile_verified_at' => 'datetime',
            'referral_prompt_dismissed_at' => 'datetime',
            'access_panel' => GuardNameEnum::class,
            'logged_in_type' => UserLoginTypeEnum::class,
        ];
    }

    public function getDefaultGuardName(): string
    {
        return GuardNameEnum::fromString(
            $this->access_panel instanceof GuardNameEnum
                ? $this->access_panel->value
                : (string) $this->access_panel
        )->value;
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class)
            ->where('type', WalletTypeEnum::CUSTOMER->value);
    }

    public function sellerWallet(): HasOne
    {
        return $this->hasOne(Wallet::class)
            ->where('type', WalletTypeEnum::SELLER->value);
    }

    public function deliveryBoyWallet(): HasOne
    {
        return $this->hasOne(Wallet::class)
            ->where('type', WalletTypeEnum::DELIVERY_BOY->value);
    }

    public function fcmTokens(): HasMany
    {
        return $this->hasMany(UserFcmToken::class);
    }

    public function routeNotificationForFirebase(): array
    {
        return $this->fcmTokens()
            ->pluck('fcm_token')
            ->filter()
            ->values()
            ->all();
    }

    public function ownedSeller(): HasOne
    {
        return $this->hasOne(Seller::class);
    }

    public function sellers(): BelongsToMany
    {
        return $this->belongsToMany(Seller::class, 'seller_user')
            ->withPivot(['position', 'status'])
            ->withTimestamps();
    }

    public function deliveryZones(): BelongsToMany
    {
        return $this->belongsToMany(
            DeliveryZone::class,
            'user_zone',
            'user_id',
            'zone_id'
        )->withTimestamps();
    }

    public function getProfileImageAttribute(): ?string
    {
        $url = $this->getFirstMediaUrl('profile_image');

        return $url !== '' ? $url : null;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('profile_image')->singleFile();
    }

    public function isFullyVerified(): bool
    {
        return $this->email_verified_at !== null
            && $this->mobile_verified_at !== null;
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPassword($token));
    }

    protected static function booted(): void
    {
        static::deleting(function (self $user): void {
            $user->clearMediaCollection('profile_image');
        });
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Models\Country.php" -Content @'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    protected $fillable = [
        'name',
        'iso3',
        'iso2',
        'numeric_code',
        'phonecode',
        'capital',
        'currency',
        'currency_name',
        'currency_symbol',
        'tld',
        'native',
        'region',
        'subregion',
        'timezones',
        'translations',
        'latitude',
        'longitude',
        'emoji',
        'emojiU',
        'flag',
        'wikiDataId',
    ];

    protected function casts(): array
    {
        return [
            'timezones' => 'array',
            'translations' => 'array',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'flag' => 'boolean',
        ];
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Models\Setting.php" -Content @'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $primaryKey = 'variable';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'variable',
        'value',
    ];

    public function getValueAttribute(?string $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        return json_decode($value, true, flags: JSON_THROW_ON_ERROR);
    }

    public function setValueAttribute(array|string|null $value): void
    {
        $this->attributes['value'] = is_array($value)
            ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
            : ($value ?? '{}');
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Models\DeliveryZone.php" -Content @'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class DeliveryZone extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'center_latitude',
        'center_longitude',
        'radius_km',
        'rush_delivery_time_per_km',
        'rush_delivery_charges',
        'delivery_time_per_km',
        'regular_delivery_charges',
        'free_delivery_amount',
        'distance_based_delivery_charges',
        'per_store_drop_off_fee',
        'handling_charges',
        'buffer_time',
        'boundary_json',
        'rush_delivery_enabled',
        'delivery_boy_base_fee',
        'delivery_boy_per_store_pickup_fee',
        'delivery_boy_distance_based_fee',
        'delivery_boy_per_order_incentive',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'boundary_json' => 'array',
            'rush_delivery_enabled' => 'boolean',
            'center_latitude' => 'decimal:8',
            'center_longitude' => 'decimal:8',
            'delivery_boy_base_fee' => 'decimal:2',
            'delivery_boy_per_store_pickup_fee' => 'decimal:2',
            'delivery_boy_distance_based_fee' => 'decimal:2',
            'delivery_boy_per_order_incentive' => 'decimal:2',
        ];
    }

    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(
            Store::class,
            'store_zone',
            'zone_id',
            'store_id'
        )->withTimestamps();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'user_zone',
            'zone_id',
            'user_id'
        )->withTimestamps();
    }

    protected static function booted(): void
    {
        static::saving(function (self $zone): void {
            if (! $zone->slug || $zone->isDirty('name')) {
                $base = Str::slug($zone->name) ?: 'zone';
                $slug = $base;
                $counter = 2;

                while (
                    self::query()
                        ->where('slug', $slug)
                        ->when(
                            $zone->exists,
                            fn ($query) => $query->where('id', '!=', $zone->id)
                        )
                        ->exists()
                ) {
                    $slug = "{$base}-{$counter}";
                    $counter++;
                }

                $zone->slug = $slug;
            }
        });
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Models\Wallet.php" -Content @'
<?php

namespace App\Models;

use App\Enums\WalletTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'balance',
        'blocked_balance',
        'currency_code',
    ];

    protected function casts(): array
    {
        return [
            'type' => WalletTypeEnum::class,
            'balance' => 'decimal:2',
            'blocked_balance' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function availableBalance(): string
    {
        return number_format(
            (float) $this->balance - (float) $this->blocked_balance,
            2,
            '.',
            ''
        );
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Models\UserFcmToken.php" -Content @'
<?php

namespace App\Models;

use App\Enums\DeviceTypeEnum;
use App\Enums\NotificationRoleTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFcmToken extends Model
{
    protected $fillable = [
        'user_id',
        'fcm_token',
        'device_type',
        'role_type',
    ];

    protected function casts(): array
    {
        return [
            'device_type' => DeviceTypeEnum::class,
            'role_type' => NotificationRoleTypeEnum::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\database\seeders\FoundationSeeder.php" -Content @'
<?php

namespace Database\Seeders;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Enums\UserLoginTypeEnum;
use App\Enums\WalletTypeEnum;
use App\Models\Country;
use App\Models\DeliveryZone;
use App\Models\Setting;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class FoundationSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::query()->firstOrCreate([
            'name' => DefaultSystemRolesEnum::SUPER_ADMIN->value,
            'guard_name' => GuardNameEnum::ADMIN->value,
        ]);

        Role::query()->firstOrCreate([
            'name' => DefaultSystemRolesEnum::SELLER->value,
            'guard_name' => GuardNameEnum::SELLER->value,
        ]);

        Role::query()->firstOrCreate([
            'name' => DefaultSystemRolesEnum::CUSTOMER->value,
            'guard_name' => GuardNameEnum::WEB->value,
        ]);

        Country::query()->updateOrCreate(
            ['iso2' => 'BD'],
            [
                'name' => 'Bangladesh',
                'iso3' => 'BGD',
                'numeric_code' => '050',
                'phonecode' => '+880',
                'capital' => 'Dhaka',
                'currency' => 'BDT',
                'currency_name' => 'Bangladeshi Taka',
                'currency_symbol' => '৳',
                'tld' => '.bd',
                'native' => 'বাংলাদেশ',
                'region' => 'Asia',
                'subregion' => 'Southern Asia',
                'timezones' => [
                    [
                        'zoneName' => 'Asia/Dhaka',
                        'gmtOffset' => 21600,
                        'gmtOffsetName' => 'UTC+06:00',
                        'abbreviation' => 'BST',
                        'tzName' => 'Bangladesh Standard Time',
                    ],
                ],
                'translations' => [
                    'bn' => 'বাংলাদেশ',
                    'en' => 'Bangladesh',
                ],
                'latitude' => 23.6850,
                'longitude' => 90.3563,
                'emoji' => '🇧🇩',
                'emojiU' => 'U+1F1E7 U+1F1E9',
                'flag' => true,
                'wikiDataId' => 'Q902',
            ]
        );

        Setting::query()->updateOrCreate(
            ['variable' => 'system'],
            [
                'value' => [
                    'appName' => 'FastSheba',
                    'systemVendorType' => 'multiple',
                    'country' => 'Bangladesh',
                    'currencyCode' => 'BDT',
                    'currencySymbol' => '৳',
                    'timezone' => 'Asia/Dhaka',
                    'defaultLanguage' => 'en',
                    'demoMode' => false,
                    'welcomeWalletBalanceAmount' => 0,
                ],
            ]
        );

        Setting::query()->updateOrCreate(
            ['variable' => 'authentication'],
            [
                'value' => [
                    'customSms' => false,
                    'firebase' => false,
                    'googleLogin' => false,
                    'appleLogin' => false,
                    'smsGateway' => '',
                    'fireBaseApiKey' => '',
                    'fireBaseAuthDomain' => '',
                    'fireBaseProjectId' => '',
                    'fireBaseStorageBucket' => '',
                    'fireBaseMessagingSenderId' => '',
                    'fireBaseAppId' => '',
                ],
            ]
        );

        Setting::query()->updateOrCreate(
            ['variable' => 'notification'],
            [
                'value' => [
                    'vapIdKey' => '',
                    'firebaseProjectId' => '',
                ],
            ]
        );

        Setting::query()->updateOrCreate(
            ['variable' => 'app'],
            [
                'value' => [
                    'customerPlaystoreLink' => '',
                    'customerAppstoreLink' => '',
                    'sellerPlaystoreLink' => '',
                    'sellerAppstoreLink' => '',
                    'riderPlaystoreLink' => '',
                    'riderAppstoreLink' => '',
                ],
            ]
        );

        DeliveryZone::query()->updateOrCreate(
            ['slug' => 'dhaka-test-zone'],
            [
                'name' => 'Dhaka Test Zone',
                'center_latitude' => 23.8103,
                'center_longitude' => 90.4125,
                'radius_km' => 25,
                'delivery_time_per_km' => 3,
                'regular_delivery_charges' => 60,
                'free_delivery_amount' => 1000,
                'distance_based_delivery_charges' => 10,
                'per_store_drop_off_fee' => 0,
                'handling_charges' => 0,
                'buffer_time' => 10,
                'rush_delivery_enabled' => false,
                'delivery_boy_base_fee' => 0,
                'delivery_boy_per_store_pickup_fee' => 0,
                'delivery_boy_distance_based_fee' => 0,
                'delivery_boy_per_order_incentive' => 0,
                'status' => 'active',
            ]
        );

        $email = env('FOUNDATION_ADMIN_EMAIL');
        $password = env('FOUNDATION_ADMIN_PASSWORD');

        if ($email && $password) {
            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => env(
                        'FOUNDATION_ADMIN_NAME',
                        'FastSheba Super Admin'
                    ),
                    'password' => $password,
                    'status' => 'active',
                    'access_panel' => GuardNameEnum::ADMIN->value,
                    'logged_in_type' => UserLoginTypeEnum::PLATFORM->value,
                    'email_verified_at' => now(),
                    'country' => 'Bangladesh',
                    'iso_2' => 'BD',
                    'country_code' => '+880',
                ]
            );

            $user->syncRoles([
                DefaultSystemRolesEnum::SUPER_ADMIN->value,
            ]);

            Wallet::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'type' => WalletTypeEnum::CUSTOMER->value,
                ],
                [
                    'balance' => 0,
                    'blocked_balance' => 0,
                    'currency_code' => 'BDT',
                ]
            );
        } elseif ($this->command) {
            $this->command->warn(
                'Super admin was not seeded. Set FOUNDATION_ADMIN_EMAIL and FOUNDATION_ADMIN_PASSWORD in .env.'
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
'@

Write-Host ""
Write-Host "HyperLocal compatibility patch written successfully." -ForegroundColor Green
Write-Host "Backup created at: $backupRoot" -ForegroundColor Cyan
Write-Host ""
Write-Host "Run next:"
Write-Host "  chcp 65001"
Write-Host "  herd php artisan optimize:clear"
Write-Host "  herd php artisan migrate:fresh --seed"
Write-Host "  herd php artisan test"
