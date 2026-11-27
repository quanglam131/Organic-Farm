<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FarmLog;
use App\Models\FarmPlot;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

/** Public information about the farm (Single Farm model). */
class FarmController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            ...collect(config('farm'))->except(['latitude', 'longitude'])->all(),
            'location' => ['lat' => config('farm.latitude'), 'lng' => config('farm.longitude')],
            'plots' => FarmPlot::query()->orderBy('code')
                ->get(['id', 'code', 'name', 'location', 'area_m2', 'certification', 'description']),
            'stats' => [
                'products' => Product::where('is_active', true)->count(),
                'diary_entries' => FarmLog::count(),
            ],
            'latest_logs' => FarmLog::query()
                ->with(['farmPlot:id,name', 'product:id,name'])
                ->latest('logged_on')
                ->limit(6)
                ->get(['id', 'farm_plot_id', 'product_id', 'type', 'description', 'logged_on', 'media_url', 'media_type']),
        ]);
    }
}
