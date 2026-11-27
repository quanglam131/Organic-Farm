<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\FarmLog;
use App\Models\ProductBatch;
use App\Services\MediaStorage;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Farming diary (nhật ký canh tác) written by farm staff, with photo or video evidence.
 * An entry belongs to a plot and, when it is about a specific harvest, to that batch:
 * the batch's QR code then shows the whole history (Tuần 2 use case 4, Tuần 3 ERD).
 */
class FarmLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = FarmLog::query()
            ->with(['farmPlot:id,code,name', 'product:id,name', 'batch:id,batch_code', 'author:id,name'])
            ->latest('logged_on')
            ->latest('id');

        foreach (['farm_plot_id', 'product_id', 'product_batch_id'] as $filter) {
            if ($value = $request->query($filter)) {
                $query->where($filter, $value);
            }
        }

        return response()->json($query->paginate(30));
    }

    public function store(Request $request, MediaStorage $media): JsonResponse
    {
        $data = $request->validate([
            'product_batch_id' => ['nullable', 'integer', 'exists:product_batches,id'],
            'farm_plot_id' => ['nullable', 'required_without:product_batch_id', 'integer', 'exists:farm_plots,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'type' => ['required', 'in:'.implode(',', FarmLog::TYPES)],
            'description' => ['required', 'string', 'max:2000'],
            'logged_on' => ['required', 'date', 'before_or_equal:today'],
            'media' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,mp4,mov', 'max:51200'],
        ], [
            'farm_plot_id.required_without' => 'Hãy chọn khu canh tác hoặc lô hàng.',
        ]);

        // A log written for a batch inherits the batch's product and plot.
        if (! empty($data['product_batch_id'])) {
            $batch = ProductBatch::findOrFail($data['product_batch_id']);
            $data['product_id'] = $batch->product_id;
            $data['farm_plot_id'] = $batch->farm_plot_id ?? ($data['farm_plot_id'] ?? null);

            if ($data['farm_plot_id'] === null) {
                throw new DomainException('Lô hàng chưa gắn khu canh tác, hãy chọn khu canh tác.');
            }
        }

        $mediaUrl = null;
        $mediaType = null;

        if ($request->hasFile('media')) {
            $file = $request->file('media');
            $mediaType = str_starts_with((string) $file->getMimeType(), 'video') ? 'video' : 'image';
            $mediaUrl = $media->store($file, 'farm-logs', $mediaType);
        }

        $log = FarmLog::create([
            ...collect($data)->except('media')->all(),
            'media_url' => $mediaUrl,
            'media_type' => $mediaType,
            'created_by' => $request->user()->id,
        ]);

        return response()->json($log->load(['farmPlot:id,code,name', 'product:id,name', 'batch:id,batch_code', 'author:id,name']), 201);
    }

    public function destroy(FarmLog $farmLog): JsonResponse
    {
        $farmLog->delete();

        return response()->json(['message' => 'Đã xóa nhật ký.']);
    }
}
