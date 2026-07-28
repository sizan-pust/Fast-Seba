<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdCampaignStat extends Model
{
    protected $fillable = [
        'ad_campaign_id','stat_date','impressions','clicks',
        'conversions','spent_amount',
    ];

    protected function casts(): array
    {
        return [
            'stat_date' => 'date',
            'impressions' => 'integer',
            'clicks' => 'integer',
            'conversions' => 'integer',
            'spent_amount' => 'decimal:4',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(
            AdCampaign::class,
            'ad_campaign_id'
        );
    }
}
