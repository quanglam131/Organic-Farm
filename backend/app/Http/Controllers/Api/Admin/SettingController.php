<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Administrator settings, e.g. when to raise the near-expiry (flash sale) alert. */
class SettingController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Setting::current());
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'expiry_alert_days' => ['sometimes', 'integer', 'between:1,14'],
            'flash_sale_discount_percent' => ['sometimes', 'integer', 'between:0,90'],
        ]);

        foreach ($data as $key => $value) {
            Setting::put($key, $value);
        }

        return response()->json(Setting::current());
    }
}
