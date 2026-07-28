<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BulkUploadJob extends Model
{
    protected $fillable = [
        'uuid','user_id','seller_id','type','operation','status',
        'original_filename','stored_path','total_rows','processed_rows',
        'successful_rows','failed_rows','failed_rows_path',
        'notify_on_finish','started_at','finished_at','metadata',
    ];

    protected function casts(): array
    {
        return [
            'total_rows' => 'integer',
            'processed_rows' => 'integer',
            'successful_rows' => 'integer',
            'failed_rows' => 'integer',
            'notify_on_finish' => 'boolean',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            $model->uuid ??= (string) Str::uuid();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }
}
