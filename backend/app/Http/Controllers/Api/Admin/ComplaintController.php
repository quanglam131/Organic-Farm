<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Sales handles complaints: accept with a refund or a redelivery, or reject with a reason. */
class ComplaintController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Complaint::query()
            ->with([
                'user:id,name,email,phone',
                // Each order line keeps its batch, so the complaint traces back to the lot (Tuần 3).
                'order:id,code,delivered_at,total',
                'order.items:id,order_id,product_name,quantity,batch_code,product_batch_id',
                'handler:id,name',
            ])
            ->latest('id');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json($query->paginate(20));
    }

    public function update(Request $request, Complaint $complaint): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:in_progress,resolved,rejected'],
            'resolution' => ['nullable', 'required_if:status,resolved', 'in:'.Complaint::RESOLUTION_REFUND.','.Complaint::RESOLUTION_REDELIVERY],
            'admin_reply' => ['nullable', 'required_if:status,rejected', 'string', 'max:2000'],
        ], [
            'resolution.required_if' => 'Hãy chọn hoàn tiền hoặc giao bù khi chấp nhận khiếu nại.',
            'admin_reply.required_if' => 'Hãy ghi lý do từ chối khiếu nại.',
        ]);

        if (in_array($complaint->status, [Complaint::STATUS_RESOLVED, Complaint::STATUS_REJECTED], true)) {
            throw new DomainException('Khiếu nại này đã được xử lý xong.');
        }

        $complaint->update([
            ...$data,
            'resolution' => $data['status'] === Complaint::STATUS_RESOLVED ? $data['resolution'] : null,
            'handled_by' => $request->user()->id,
            'handled_at' => now(),
        ]);

        return response()->json($complaint->fresh()->load(['user:id,name,email', 'order:id,code', 'handler:id,name']));
    }
}
