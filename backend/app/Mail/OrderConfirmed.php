<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OrderConfirmed extends Mailable
{
    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: config('farm.name').' - Xác nhận đơn hàng '.$this->order->code);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.order-confirmed');
    }
}
