<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Distance-based shipping fee (Tuần 6): the distance from the farm to the customer's address
 * is looked up on the server, never taken from the browser when a lookup succeeds.
 *
 *  - GOOGLE_MAPS_API_KEY set: Google Distance Matrix (driving distance).
 *  - otherwise: OpenStreetMap Nominatim geocoding + straight-line distance x road factor.
 *  - lookup failed: the caller may fall back to the distance the customer typed.
 */
class ShippingService
{
    public function fee(int $subtotal, int $distanceKm): int
    {
        $cfg = config('shop.shipping');

        if ($subtotal >= $cfg['free_threshold']) {
            return 0;
        }

        $distanceKm = min(max($distanceKm, 0), $cfg['max_km']);
        $extraKm = max(0, $distanceKm - $cfg['included_km']);

        return $cfg['base_fee'] + $extraKm * $cfg['per_km_fee'];
    }

    /**
     * Distance in km from the farm to an address, cached for a day per address.
     *
     * @return array{distance_km: int, source: string}|null null when the address cannot be found
     */
    public function distanceTo(string $address): ?array
    {
        $address = trim($address);
        if (mb_strlen($address) < 5) {
            return null;
        }

        $key = 'shipping.distance.'.md5(mb_strtolower($address));

        $cached = Cache::get($key);
        if ($cached !== null) {
            return $cached;
        }

        $result = $this->lookup($address);
        if ($result !== null) {
            Cache::put($key, $result, now()->addDay());
        }

        return $result;
    }

    /** Great-circle distance between two points, in km. */
    public function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(min(1, sqrt($a)));
    }

    private function lookup(string $address): ?array
    {
        $farmLat = (float) config('farm.latitude');
        $farmLng = (float) config('farm.longitude');
        $timeout = (int) config('services.maps.timeout', 5);

        try {
            if ($googleKey = config('services.maps.google_key')) {
                $element = Http::timeout($timeout)
                    ->get('https://maps.googleapis.com/maps/api/distancematrix/json', [
                        'origins' => $farmLat.','.$farmLng,
                        'destinations' => $address,
                        'mode' => 'driving',
                        'language' => 'vi',
                        'key' => $googleKey,
                    ])
                    ->throw()
                    ->json('rows.0.elements.0');

                if (($element['status'] ?? null) === 'OK') {
                    return ['distance_km' => (int) ceil($element['distance']['value'] / 1000), 'source' => 'google'];
                }

                return null;
            }

            $place = Http::timeout($timeout)
                ->withHeaders(['User-Agent' => config('farm.name').' shop (course project)'])
                ->get(config('services.maps.nominatim_url'), [
                    'q' => $address,
                    'format' => 'json',
                    'limit' => 1,
                    'countrycodes' => 'vn',
                ])
                ->throw()
                ->json('0');

            if (! isset($place['lat'], $place['lon'])) {
                return null;
            }

            $straight = $this->haversineKm($farmLat, $farmLng, (float) $place['lat'], (float) $place['lon']);

            return [
                'distance_km' => (int) ceil($straight * (float) config('services.maps.road_factor', 1.3)),
                'source' => 'osm',
            ];
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
