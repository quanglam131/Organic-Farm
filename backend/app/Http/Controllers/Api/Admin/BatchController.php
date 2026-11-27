<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Services\InventoryService;
use App\Services\MediaStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/** Batch (lô) management: receive harvests, record waste, preview FEFO picks. */
class BatchController extends Controller
{
    public function index(Product $product): JsonResponse
    {
        return response()->json(
            $product->batches()->with('farmPlot:id,name')->orderBy('expires_on')->get()
        );
    }

    public function store(Request $request, Product $product, InventoryService $inventory): JsonResponse
    {
        $data = $request->validate([
            'farm_plot_id' => ['nullable', 'integer', 'exists:farm_plots,id'],
            'batch_code' => ['nullable', 'string', 'max:50', 'unique:product_batches,batch_code'],
            'quantity_received' => ['required', 'integer', 'min:1'],
            'harvested_on' => ['required', 'date'],
            'expires_on' => ['required', 'date', 'after_or_equal:harvested_on'],
        ]);

        $batch = $inventory->receive(
            [...$data, 'product_id' => $product->id],
            $request->user()->id,
        );

        return response()->json($batch->load('farmPlot:id,name'), 201);
    }

    /** Which batches FEFO would pick for a given quantity, without changing stock. */
    public function fefoPreview(Request $request, Product $product, InventoryService $inventory): JsonResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1']]);

        $plan = collect($inventory->allocateFefo($product, $data['quantity']))->map(fn ($line) => [
            'batch_id' => $line['batch']->id,
            'batch_code' => $line['batch']->batch_code,
            'expires_on' => $line['batch']->expires_on->toDateString(),
            'quantity' => $line['quantity'],
        ]);

        return response()->json(['product' => $product->name, 'plan' => $plan->values()]);
    }

    /** Record waste (xuất hủy / hao hụt) from one batch, with an optional photo taken on the spot. */
    public function waste(Request $request, ProductBatch $batch, InventoryService $inventory, MediaStorage $media): JsonResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $photoUrl = $request->hasFile('photo') ? $media->store($request->file('photo'), 'waste') : null;

        $batch = $inventory->recordWaste($batch, $data['quantity'], $data['reason'], $request->user()->id, $photoUrl);

        return response()->json($batch->load(['product:id,name,unit', 'farmPlot:id,name']));
    }

    /** Every batch, for the staff dashboard: filter by status or product name. */
    public function all(Request $request): JsonResponse
    {
        $query = ProductBatch::query()
            ->with(['product:id,name,unit,image_url', 'farmPlot:id,name'])
            ->orderBy('expires_on');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        } else {
            $query->where('status', ProductBatch::STATUS_AVAILABLE);
        }
        if ($term = trim((string) $request->query('q'))) {
            $query->whereHas('product', fn ($q) => $q->where('name', 'like', "%{$term}%"));
        }

        return response()->json($query->paginate(30));
    }

    /** One batch with its diary entries and stock history. */
    public function show(ProductBatch $batch): JsonResponse
    {
        return response()->json($batch->load([
            'product:id,name,unit,image_url',
            'farmPlot:id,code,name',
            'logs' => fn ($q) => $q->with('author:id,name')->latest('logged_on'),
            'movements' => fn ($q) => $q->with('user:id,name')->latest('id'),
        ]));
    }

    public function expireDue(InventoryService $inventory): JsonResponse
    {
        return response()->json(['expired_batches' => $inventory->expireDueBatches()]);
    }

    /**
     * QR Code (SVG) that opens the batch's traceability page on the storefront.
     * The code points to the public /trace/{trace_code} page, which shows the farm diary.
     */
    public function qr(ProductBatch $batch): Response
    {
        $url = rtrim(config('payments.frontend_url'), '/').'/trace/'.$batch->trace_code;

        $svg = QrCode::format('svg')->size(300)->margin(1)->generate($url);

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'inline; filename="'.$batch->trace_code.'.svg"',
        ]);
    }
}
