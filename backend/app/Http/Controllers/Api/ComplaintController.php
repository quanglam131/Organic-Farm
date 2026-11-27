<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\Order;
use App\Services\MediaStorage;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Complaints about delivered orders (Tuần 2 use case 3, Tuần 6):
 *  - only for a delivered order, within 24 hours of delivery (fresh produce spoils fast);
 *  - a photo is mandatory: .jpg or .png, at most 5 MB (checked in the browser and here).
 */
class ComplaintController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $request->user()->complaints()->with('order:id,code')->latest('id')->get()
        );
    }

    public function store(Request $request, MediaStorage $media): JsonResponse
    {
        $data = $request->validate([
            'order_code' => ['required', 'string'],
            'reason' => ['required', 'in:'.implode(',', array_keys(Complaint::REASONS))],
            'content' => ['required', 'string', 'max:2000'],
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
        ], [
            'image.required' => 'Vui lòng đính kèm ảnh minh chứng.',
            'image.mimes' => 'Ảnh minh chứng phải là file .jpg hoặc .png.',
            'image.max' => 'Ảnh minh chứng không được lớn hơn 5 MB.',
        ]);

        $user = $request->user();
        $order = $user->orders()->where('code', $data['order_code'])->firstOrFail();

        if ($order->status !== Order::STATUS_DELIVERED) {
            throw new DomainException('Chỉ gửi khiếu nại được cho đơn hàng đã giao.');
        }
        if (! $order->complaintWindowOpen()) {
            throw new DomainException(
                'Đã quá '.Order::COMPLAINT_WINDOW_HOURS.' giờ kể từ khi nhận hàng nên hệ thống không nhận khiếu nại cho đơn này.'
            );
        }

        $complaint = Complaint::create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'reason' => $data['reason'],
            'subject' => Complaint::REASONS[$data['reason']].' - đơn '.$order->code,
            'content' => $data['content'],
            'image_url' => $media->store($request->file('image'), 'complaints'),
            'status' => Complaint::STATUS_OPEN,
        ]);

        return response()->json($complaint->load('order:id,code'), 201);
    }
}
