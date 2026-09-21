<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;

class RouteEstimateController extends ApiController
{
    public function estimate(Request $request)
    {
        $data = $request->validate(['origin.latitude' => ['required', 'numeric', 'between:-90,90'], 'origin.longitude' => ['required', 'numeric', 'between:-180,180'], 'destination.latitude' => ['required', 'numeric', 'between:-90,90'], 'destination.longitude' => ['required', 'numeric', 'between:-180,180']]);
        $distanceKm = $this->distance((float) $data['origin']['latitude'], (float) $data['origin']['longitude'], (float) $data['destination']['latitude'], (float) $data['destination']['longitude']);
        $durationMinutes = (int) max(1, ceil($distanceKm / 25 * 60));

        return response()->json(['distanceKm' => round($distanceKm, 2), 'durationMinutes' => $durationMinutes, 'source' => 'haversine_fallback']);
    }

    private function distance(float $latitudeA, float $longitudeA, float $latitudeB, float $longitudeB): float
    {
        $earthRadiusKm = 6371.0;
        $latitudeDelta = deg2rad($latitudeB - $latitudeA);
        $longitudeDelta = deg2rad($longitudeB - $longitudeA);
        $a = sin($latitudeDelta / 2) ** 2 + cos(deg2rad($latitudeA)) * cos(deg2rad($latitudeB)) * sin($longitudeDelta / 2) ** 2;

        return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
