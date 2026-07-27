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
$backupRoot = Join-Path $BackendPath "_phase1_backups\$timestamp"
New-Item -ItemType Directory -Force -Path $backupRoot | Out-Null

$files = @{
    Users        = Get-OneFile "*add_fastsheba_fields_to_users_table.php"
    Countries    = Get-OneFile "*create_countries_table.php"
    Settings     = Get-OneFile "*create_settings_table.php"
    Zones        = Get-OneFile "*create_delivery_zones_table.php"
    Wallets      = Get-OneFile "*create_wallets_table.php"
    Sellers      = Get-OneFile "*create_sellers_table.php"
    Stores       = Get-OneFile "*create_stores_table.php"
    SellerUser   = Get-OneFile "*create_seller_user_table.php"
    StoreZone    = Get-OneFile "*create_store_zone_table.php"
    UserZone     = Get-OneFile "*create_user_zone_table.php"
}

$pathsToBackup = @(
    $files.Values
    "$BackendPath\app\Models\User.php"
    "$BackendPath\app\Models\Country.php"
    "$BackendPath\app\Models\Setting.php"
    "$BackendPath\app\Models\DeliveryZone.php"
    "$BackendPath\app\Models\Wallet.php"
    "$BackendPath\app\Models\Seller.php"
    "$BackendPath\app\Models\Store.php"
    "$BackendPath\database\seeders\FoundationSeeder.php"
    "$BackendPath\database\seeders\DatabaseSeeder.php"
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
            $table->string('mobile', 20)->nullable()->unique()->after('email');
            $table->string('country_code', 10)->nullable()->after('mobile');
            $table->char('iso2', 2)->nullable()->after('country_code');

            $table->string('referral_code', 32)->nullable()->unique()->after('iso2');
            $table->foreignId('referred_by_user_id')
                ->nullable()
                ->after('referral_code')
                ->constrained('users')
                ->nullOnDelete();

            $table->decimal('reward_points', 12, 2)->default(0);
            $table->string('status', 20)->default('active')->index();
            $table->string('access_panel', 20)->default('customer')->index();
            $table->string('logged_in_type', 20)->nullable();

            $table->timestamp('mobile_verified_at')->nullable();
            $table->timestamp('referral_prompt_dismissed_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();

            $table->softDeletes();
            $table->index(['status', 'access_panel']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['status', 'access_panel']);
            $table->dropForeign(['referred_by_user_id']);

            $table->dropColumn([
                'firebase_uid',
                'mobile',
                'country_code',
                'iso2',
                'referral_code',
                'referred_by_user_id',
                'reward_points',
                'status',
                'access_panel',
                'logged_in_type',
                'mobile_verified_at',
                'referral_prompt_dismissed_at',
                'last_login_at',
                'last_login_ip',
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
            $table->boolean('is_active')->default(true)->index();
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
            $table->string('variable', 100)->primary();
            $table->json('value')->nullable();
            $table->boolean('is_public')->default(false)->index();
            $table->string('description')->nullable();
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
            $table->string('name');
            $table->string('slug')->unique();

            $table->decimal('center_latitude', 10, 8)->nullable();
            $table->decimal('center_longitude', 11, 8)->nullable();
            $table->decimal('radius_km', 8, 2)->default(0);
            $table->json('boundary_json')->nullable();

            $table->unsignedInteger('delivery_time_per_km')->default(3);
            $table->unsignedInteger('buffer_time')->default(10);

            $table->boolean('rush_delivery_enabled')->default(false);
            $table->unsignedInteger('rush_delivery_time_per_km')->nullable();
            $table->decimal('rush_delivery_charges', 12, 2)->nullable();

            $table->decimal('regular_delivery_charges', 12, 2)->default(0);
            $table->decimal('free_delivery_amount', 12, 2)->nullable();
            $table->decimal('distance_based_delivery_charges', 12, 2)->default(0);
            $table->decimal('per_store_drop_off_fee', 12, 2)->default(0);
            $table->decimal('handling_charges', 12, 2)->default(0);

            $table->decimal('rider_base_fee', 12, 2)->default(0);
            $table->decimal('rider_per_store_pickup_fee', 12, 2)->default(0);
            $table->decimal('rider_distance_based_fee', 12, 2)->default(0);
            $table->decimal('rider_per_order_incentive', 12, 2)->default(0);

            $table->char('currency_code', 3)->default('BDT');
            $table->string('status', 20)->default('active')->index();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['status', 'rush_delivery_enabled']);
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
            $table->decimal('balance', 16, 2)->default(0);
            $table->char('currency_code', 3)->default('BDT');
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['user_id', 'type']);
            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
'@

Write-Utf8NoBom -Path $files.Sellers -Content @'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sellers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('business_name');
            $table->string('legal_name')->nullable();
            $table->string('trade_license_number')->nullable()->index();
            $table->string('tax_number')->nullable();

            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('landmark', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('zipcode', 20)->nullable();
            $table->string('country', 100)->default('Bangladesh');
            $table->string('country_code', 10)->default('+880');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            $table->decimal('commission_rate', 5, 2)->default(0);
            $table->string('verification_status', 20)->default('pending')->index();
            $table->string('visibility_status', 20)->default('draft')->index();
            $table->string('status', 20)->default('active')->index();

            $table->json('metadata')->nullable();
            $table->unsignedInteger('post_accept_cancel_count')->default(0);

            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['verification_status', 'visibility_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sellers');
    }
};
'@

Write-Utf8NoBom -Path $files.Stores -Content @'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('slug', 300)->unique();

            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('landmark', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('zipcode', 20)->nullable();
            $table->string('country', 100)->default('Bangladesh');
            $table->string('country_code', 10)->default('+880');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            $table->string('contact_email')->nullable();
            $table->string('contact_number', 20)->nullable();
            $table->text('description')->nullable();
            $table->json('timing')->nullable();

            $table->string('tax_name')->nullable();
            $table->string('tax_number')->nullable();

            $table->text('bank_name')->nullable();
            $table->text('bank_branch_code')->nullable();
            $table->text('account_holder_name')->nullable();
            $table->text('account_number')->nullable();
            $table->text('routing_number')->nullable();
            $table->string('bank_account_type', 20)->nullable();

            $table->char('currency_code', 3)->default('BDT');
            $table->string('status', 20)->default('online')->index();
            $table->decimal('max_delivery_distance', 8, 2)->default(10);
            $table->unsignedInteger('order_preparation_time')->default(15);

            $table->string('promotional_text', 1024)->nullable();
            $table->text('about_us')->nullable();
            $table->text('return_replacement_policy')->nullable();
            $table->text('refund_policy')->nullable();
            $table->text('terms_and_conditions')->nullable();
            $table->text('delivery_policy')->nullable();

            $table->decimal('domestic_shipping_charges', 12, 2)->nullable();
            $table->decimal('international_shipping_charges', 12, 2)->nullable();

            $table->json('metadata')->nullable();
            $table->string('verification_status', 20)->default('pending')->index();
            $table->string('visibility_status', 20)->default('draft')->index();
            $table->boolean('is_recommended')->default(false)->index();
            $table->string('fulfillment_type', 20)->default('hyperlocal');

            $table->string('pos_upi_vpa')->nullable();
            $table->string('pos_upi_payee_name')->nullable();
            $table->json('pos_payment_config')->nullable();
            $table->json('receipt_template')->nullable();

            $table->boolean('allows_pickup')->default(false);
            $table->string('pickup_instructions', 500)->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['seller_id', 'status']);
            $table->index(['verification_status', 'visibility_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
'@

Write-Utf8NoBom -Path $files.SellerUser -Content @'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('position')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['seller_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_user');
    }
};
'@

Write-Utf8NoBom -Path $files.StoreZone -Content @'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_zone', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('zone_id')->constrained('delivery_zones')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['store_id', 'zone_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_zone');
    }
};
'@

Write-Utf8NoBom -Path $files.UserZone -Content @'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_zone', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('zone_id')->constrained('delivery_zones')->cascadeOnDelete();
            $table->timestamps();

            $table->unique('user_id');
            $table->unique(['user_id', 'zone_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_zone');
    }
};
'@

Write-Utf8NoBom -Path "$BackendPath\app\Models\User.php" -Content @'
<?php

namespace App\Models;

use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    protected $fillable = [
        'name',
        'email',
        'password',
        'firebase_uid',
        'mobile',
        'country_code',
        'iso2',
        'referral_code',
        'referred_by_user_id',
        'reward_points',
        'status',
        'access_panel',
        'logged_in_type',
        'email_verified_at',
        'mobile_verified_at',
        'referral_prompt_dismissed_at',
        'last_login_at',
        'last_login_ip',
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
            'last_login_at' => 'datetime',
        ];
    }

    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class);
    }

    public function customerWallet(): HasOne
    {
        return $this->hasOne(Wallet::class)->where('type', 'customer');
    }

    public function sellerWallet(): HasOne
    {
        return $this->hasOne(Wallet::class)->where('type', 'seller');
    }

    public function riderWallet(): HasOne
    {
        return $this->hasOne(Wallet::class)->where('type', 'rider');
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
        return $this->belongsToMany(DeliveryZone::class, 'user_zone', 'user_id', 'zone_id')
            ->withTimestamps();
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(self::class, 'referred_by_user_id');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(self::class, 'referred_by_user_id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('profile_image')->singleFile();
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Models\Country.php" -Content @'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    use HasFactory;

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
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'timezones' => 'array',
            'translations' => 'array',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'is_active' => 'boolean',
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
        'is_public',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'is_public' => 'boolean',
        ];
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Models\DeliveryZone.php" -Content @'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class DeliveryZone extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'center_latitude',
        'center_longitude',
        'radius_km',
        'boundary_json',
        'delivery_time_per_km',
        'buffer_time',
        'rush_delivery_enabled',
        'rush_delivery_time_per_km',
        'rush_delivery_charges',
        'regular_delivery_charges',
        'free_delivery_amount',
        'distance_based_delivery_charges',
        'per_store_drop_off_fee',
        'handling_charges',
        'rider_base_fee',
        'rider_per_store_pickup_fee',
        'rider_distance_based_fee',
        'rider_per_order_incentive',
        'currency_code',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'boundary_json' => 'array',
            'rush_delivery_enabled' => 'boolean',
            'center_latitude' => 'decimal:8',
            'center_longitude' => 'decimal:8',
            'radius_km' => 'decimal:2',
            'rush_delivery_charges' => 'decimal:2',
            'regular_delivery_charges' => 'decimal:2',
            'free_delivery_amount' => 'decimal:2',
            'distance_based_delivery_charges' => 'decimal:2',
            'per_store_drop_off_fee' => 'decimal:2',
            'handling_charges' => 'decimal:2',
            'rider_base_fee' => 'decimal:2',
            'rider_per_store_pickup_fee' => 'decimal:2',
            'rider_distance_based_fee' => 'decimal:2',
            'rider_per_order_incentive' => 'decimal:2',
        ];
    }

    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'store_zone', 'zone_id', 'store_id')
            ->withTimestamps();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_zone', 'zone_id', 'user_id')
            ->withTimestamps();
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
                        ->when($zone->exists, fn ($query) => $query->where('id', '!=', $zone->id))
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

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wallet extends Model
{
    use HasFactory;

    public const TYPE_CUSTOMER = 'customer';
    public const TYPE_SELLER = 'seller';
    public const TYPE_RIDER = 'rider';

    protected $fillable = [
        'user_id',
        'type',
        'balance',
        'currency_code',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Models\Seller.php" -Content @'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Seller extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'business_name',
        'legal_name',
        'trade_license_number',
        'tax_number',
        'address',
        'city',
        'landmark',
        'state',
        'zipcode',
        'country',
        'country_code',
        'latitude',
        'longitude',
        'commission_rate',
        'verification_status',
        'visibility_status',
        'status',
        'metadata',
        'post_accept_cancel_count',
        'verified_at',
        'verified_by',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'commission_rate' => 'decimal:2',
            'metadata' => 'array',
            'verified_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'seller_user')
            ->withPivot(['position', 'status'])
            ->withTimestamps();
    }

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class, 'user_id', 'user_id')
            ->where('type', Wallet::TYPE_SELLER);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('business_license')->singleFile();
        $this->addMediaCollection('articles_of_incorporation')->singleFile();
        $this->addMediaCollection('national_identity_card')->singleFile();
        $this->addMediaCollection('authorized_signature')->singleFile();
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Models\Store.php" -Content @'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Store extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;
    use SoftDeletes;

    protected $fillable = [
        'seller_id',
        'name',
        'slug',
        'address',
        'city',
        'landmark',
        'state',
        'zipcode',
        'country',
        'country_code',
        'latitude',
        'longitude',
        'contact_email',
        'contact_number',
        'description',
        'timing',
        'tax_name',
        'tax_number',
        'bank_name',
        'bank_branch_code',
        'account_holder_name',
        'account_number',
        'routing_number',
        'bank_account_type',
        'currency_code',
        'status',
        'max_delivery_distance',
        'order_preparation_time',
        'promotional_text',
        'about_us',
        'return_replacement_policy',
        'refund_policy',
        'terms_and_conditions',
        'delivery_policy',
        'domestic_shipping_charges',
        'international_shipping_charges',
        'metadata',
        'verification_status',
        'visibility_status',
        'is_recommended',
        'fulfillment_type',
        'pos_upi_vpa',
        'pos_upi_payee_name',
        'pos_payment_config',
        'receipt_template',
        'allows_pickup',
        'pickup_instructions',
    ];

    protected $hidden = [
        'bank_name',
        'bank_branch_code',
        'account_holder_name',
        'account_number',
        'routing_number',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'timing' => 'array',
            'bank_name' => 'encrypted',
            'bank_branch_code' => 'encrypted',
            'account_holder_name' => 'encrypted',
            'account_number' => 'encrypted',
            'routing_number' => 'encrypted',
            'max_delivery_distance' => 'decimal:2',
            'domestic_shipping_charges' => 'decimal:2',
            'international_shipping_charges' => 'decimal:2',
            'metadata' => 'array',
            'is_recommended' => 'boolean',
            'pos_payment_config' => 'array',
            'receipt_template' => 'array',
            'allows_pickup' => 'boolean',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function zones(): BelongsToMany
    {
        return $this->belongsToMany(DeliveryZone::class, 'store_zone', 'store_id', 'zone_id')
            ->withTimestamps();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('store_logo')->singleFile();
        $this->addMediaCollection('store_banner')->singleFile();
        $this->addMediaCollection('address_proof')->singleFile();
        $this->addMediaCollection('voided_check')->singleFile();
    }

    protected static function booted(): void
    {
        static::saving(function (self $store): void {
            if (! $store->slug || $store->isDirty('name')) {
                $base = Str::slug($store->name) ?: 'store';
                $slug = $base;
                $counter = 2;

                while (
                    self::query()
                        ->where('slug', $slug)
                        ->when($store->exists, fn ($query) => $query->where('id', '!=', $store->id))
                        ->exists()
                ) {
                    $slug = "{$base}-{$counter}";
                    $counter++;
                }

                $store->slug = $slug;
            }
        });
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\database\seeders\FoundationSeeder.php" -Content @'
<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\DeliveryZone;
use App\Models\Setting;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class FoundationSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'dashboard.view',
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'roles.manage',
            'settings.manage',
            'countries.manage',
            'delivery-zones.view',
            'delivery-zones.create',
            'delivery-zones.update',
            'delivery-zones.delete',
            'sellers.view',
            'sellers.create',
            'sellers.update',
            'sellers.verify',
            'stores.view',
            'stores.create',
            'stores.update',
            'stores.verify',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $superAdmin = Role::findOrCreate('super-admin', 'web');
        $admin = Role::findOrCreate('admin', 'web');
        Role::findOrCreate('customer', 'web');
        Role::findOrCreate('seller-owner', 'web');
        Role::findOrCreate('seller-staff', 'web');
        Role::findOrCreate('rider', 'web');

        $superAdmin->syncPermissions(Permission::query()->pluck('name')->all());

        $admin->syncPermissions([
            'dashboard.view',
            'users.view',
            'users.create',
            'users.update',
            'settings.manage',
            'countries.manage',
            'delivery-zones.view',
            'delivery-zones.create',
            'delivery-zones.update',
            'sellers.view',
            'sellers.update',
            'sellers.verify',
            'stores.view',
            'stores.update',
            'stores.verify',
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
                'is_active' => true,
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
                ],
                'is_public' => true,
                'description' => 'Public system and localization settings.',
            ]
        );

        Setting::query()->updateOrCreate(
            ['variable' => 'auth'],
            [
                'value' => [
                    'emailLoginEnabled' => true,
                    'phoneLoginEnabled' => false,
                    'googleLoginEnabled' => false,
                    'appleLoginEnabled' => false,
                    'requireEmailVerification' => false,
                    'requireMobileVerification' => false,
                ],
                'is_public' => true,
                'description' => 'Authentication provider settings.',
            ]
        );

        Setting::query()->updateOrCreate(
            ['variable' => 'business'],
            [
                'value' => [
                    'cashOnDeliveryEnabled' => true,
                    'pickupEnabled' => true,
                    'rushDeliveryEnabled' => false,
                ],
                'is_public' => true,
                'description' => 'Core business feature flags.',
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
                'buffer_time' => 10,
                'regular_delivery_charges' => 60,
                'distance_based_delivery_charges' => 10,
                'free_delivery_amount' => 1000,
                'currency_code' => 'BDT',
                'status' => 'active',
            ]
        );

        $email = env('FOUNDATION_ADMIN_EMAIL');
        $password = env('FOUNDATION_ADMIN_PASSWORD');

        if ($email && $password) {
            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => env('FOUNDATION_ADMIN_NAME', 'FastSheba Super Admin'),
                    'password' => $password,
                    'status' => 'active',
                    'access_panel' => 'admin',
                    'logged_in_type' => 'platform',
                    'email_verified_at' => now(),
                    'iso2' => 'BD',
                    'country_code' => '+880',
                ]
            );

            $user->syncRoles(['super-admin']);

            Wallet::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'type' => Wallet::TYPE_CUSTOMER,
                ],
                [
                    'balance' => 0,
                    'currency_code' => 'BDT',
                    'status' => 'active',
                ]
            );
        } elseif ($this->command) {
            $this->command->warn(
                'Super admin was not seeded. Set FOUNDATION_ADMIN_EMAIL and FOUNDATION_ADMIN_PASSWORD in .env.'
            );
        }
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\database\seeders\DatabaseSeeder.php" -Content @'
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            FoundationSeeder::class,
        ]);
    }
}
'@

Write-Host ""
Write-Host "Phase 1 foundation files have been written successfully." -ForegroundColor Green
Write-Host "Backup created at: $backupRoot" -ForegroundColor Cyan
Write-Host ""
Write-Host "Next:"
Write-Host "1. Add FOUNDATION_ADMIN_* values to .env"
Write-Host "2. Run: herd php artisan migrate:fresh --seed"
Write-Host "3. Run: herd php artisan test"
