<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FarmLog;
use App\Models\ProductBatch;
use Illuminate\Http\JsonResponse;

/** Public traceability page: what the QR code on a batch reveals to the customer. */
class TraceController extends Controller
{
    public function show(string $traceCode): JsonResponse
    {
        $batch = ProductBatch::query()
            ->with([
                'product:id,name,slug,unit,image_url',
                'farmPlot:id,code,name,location,area_m2,certification,description',
            ])
            ->where('trace_code', strtoupper($traceCode))
            ->firstOrFail();

        // This batch's own diary, then general entries of its plot for the same crop.
        $logs = FarmLog::query()
            ->with('author:id,name')
            ->where(fn ($q) => $q->where('product_batch_id', $batch->id)
                ->orWhere(fn ($q) => $q->whereNull('product_batch_id')
                    ->where('farm_plot_id', $batch->farm_plot_id)
                    ->where(fn ($q) => $q->whereNull('product_id')->orWhere('product_id', $batch->product_id))))
            ->latest('logged_on')
            ->limit(30)
            ->get();

        return response()->json([
            'trace_code' => $batch->trace_code,
            'batch_code' => $batch->batch_code,
            'product' => $batch->product,
            'farm_plot' => $batch->farmPlot,
            'harvested_on' => $batch->harvested_on->toDateString(),
            'expires_on' => $batch->expires_on->toDateString(),
            'is_expired' => ! $batch->expires_on->isFuture(),
            'quantity_received' => $batch->quantity_received,
            'logs' => $logs,
            'farm' => [
                'name' => config('farm.name'),
                'address' => config('farm.address'),
                'certifications' => config('farm.certifications'),
            ],
        ]);
    }
}
