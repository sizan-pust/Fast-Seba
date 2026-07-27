<?php

namespace App\Services;

use App\Enums\SettingTypeEnum;
use App\Http\Resources\SettingResource;
use App\Models\Setting;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class SettingService
{
    public function getAllSettings(): Collection
    {
        return collect(SettingTypeEnum::values())
            ->map(fn (string $variable) => $this->getSettingByVariable($variable))
            ->filter()
            ->values();
    }

    public function getSettingByVariable(string $variable): ?JsonResource
    {
        if (! in_array($variable, SettingTypeEnum::values(), true)) {
            return null;
        }

        $setting = Setting::query()->where('variable', $variable)->first();

        return $setting ? new SettingResource($setting) : null;
    }

    public function getSettingValues(string $variable): array
    {
        return Cache::remember(
            "settings:{$variable}",
            now()->addMinutes(30),
            fn (): array => Setting::query()
                ->where('variable', $variable)
                ->first()?->value ?? []
        );
    }

    public function clearSettingCache(string $variable): void
    {
        Cache::forget("settings:{$variable}");
    }
}