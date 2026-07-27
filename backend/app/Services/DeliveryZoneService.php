<?php

namespace App\Services;

use App\Models\DeliveryZone;
use Illuminate\Support\Collection;

class DeliveryZoneService
{
    public static function validateCoordinates(
        float $latitude,
        float $longitude
    ): bool {
        return $latitude >= -90
            && $latitude <= 90
            && $longitude >= -180
            && $longitude <= 180;
    }

    public static function getZonesAtPoint(
        float $latitude,
        float $longitude
    ): array {
        $zones = DeliveryZone::query()
            ->where('status', 'active')
            ->get()
            ->filter(
                fn (DeliveryZone $zone): bool => self::containsPoint(
                    $zone,
                    $latitude,
                    $longitude
                )
            )
            ->values();

        $first = $zones->first();

        return [
            'zone_count' => $zones->count(),
            'zone' => $first,
            'zone_id' => $first?->id,
        ];
    }

    public static function existsAtPoint(
        float $latitude,
        float $longitude
    ): bool {
        return self::getZonesAtPoint($latitude, $longitude)['zone_count'] > 0;
    }

    private static function containsPoint(
        DeliveryZone $zone,
        float $latitude,
        float $longitude
    ): bool {
        $boundary = $zone->boundary_json;

        if (is_array($boundary) && count($boundary) >= 3) {
            return self::pointInPolygon($latitude, $longitude, $boundary);
        }

        return self::distanceInKilometres(
            $latitude,
            $longitude,
            (float) $zone->center_latitude,
            (float) $zone->center_longitude
        ) <= (float) $zone->radius_km;
    }

    private static function distanceInKilometres(
        float $lat1,
        float $lng1,
        float $lat2,
        float $lng2
    ): float {
        $earthRadius = 6371.0;

        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1))
            * cos(deg2rad($lat2))
            * sin($lngDelta / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private static function pointInPolygon(
        float $latitude,
        float $longitude,
        array $polygon
    ): bool {
        $inside = false;
        $count = count($polygon);

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $pointI = self::normalizeBoundaryPoint($polygon[$i]);
            $pointJ = self::normalizeBoundaryPoint($polygon[$j]);

            if (! $pointI || ! $pointJ) {
                continue;
            }

            [$latI, $lngI] = $pointI;
            [$latJ, $lngJ] = $pointJ;

            $intersects = (($latI > $latitude) !== ($latJ > $latitude))
                && (
                    $longitude
                    < ($lngJ - $lngI)
                    * ($latitude - $latI)
                    / (($latJ - $latI) ?: PHP_FLOAT_EPSILON)
                    + $lngI
                );

            if ($intersects) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    private static function normalizeBoundaryPoint(mixed $point): ?array
    {
        if (! is_array($point)) {
            return null;
        }

        $lat = $point['lat'] ?? $point['latitude'] ?? $point[0] ?? null;
        $lng = $point['lng'] ?? $point['longitude'] ?? $point[1] ?? null;

        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return null;
        }

        return [(float) $lat, (float) $lng];
    }
}