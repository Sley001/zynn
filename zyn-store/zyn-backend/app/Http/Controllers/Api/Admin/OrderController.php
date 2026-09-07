<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\TelegramPaymentAlertStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min(max((int) $request->integer('per_page', 25), 1), 100);

        $orders = Order::query()
            ->with(['items', 'payment', 'telegramPaymentAlert'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%'.$request->string('search').'%';
                $query->where(function ($query) use ($search): void {
                    $query->where('order_number', 'ilike', $search)
                        ->orWhere('phone', 'ilike', $search)
                        ->orWhere('customer_name', 'ilike', $search);
                });
            })
            ->latest()
            ->paginate($perPage);

        return OrderResource::collection($orders);
    }

    public function show(Order $order): OrderResource
    {
        return new OrderResource($order->load(['items', 'payment', 'telegramPaymentAlert']));
    }

    public function update(UpdateOrderRequest $request, Order $order): OrderResource
    {
        $validated = $request->validated();
        $order->loadMissing('telegramPaymentAlert');

        if (
            in_array($order->payment_method, [PaymentMethod::Khqr, PaymentMethod::PayWay], true)
            && (array_key_exists('payment_status', $validated)
                || array_key_exists('transaction_reference', $validated))
        ) {
            return abort(422, 'Electronic payment status and transaction ID are read-only and come from the authenticated Telegram alert.');
        }

        if (
            ($validated['status'] ?? null) === OrderStatus::Shipped->value
            && $order->telegramPaymentAlert
            && $order->telegramPaymentAlert->status !== TelegramPaymentAlertStatus::Matched
            && ! ($validated['force_ship_without_verified_payment'] ?? false)
        ) {
            return abort(422, 'This Telegram-detected payment still needs review. Confirm before marking it shipped.');
        }

        $order->update(array_filter([
            'status' => $validated['status'] ?? null,
            'admin_note' => array_key_exists('admin_note', $validated)
                ? $validated['admin_note']
                : null,
        ], fn ($value, $key) => $key === 'admin_note'
            ? array_key_exists('admin_note', $validated)
            : $value !== null, ARRAY_FILTER_USE_BOTH));

        if (array_key_exists('payment_status', $validated) || array_key_exists('transaction_reference', $validated)) {
            $paymentData = [];

            if (array_key_exists('payment_status', $validated)) {
                $paymentData['status'] = $validated['payment_status'];
                $paymentData['paid_at'] = $validated['payment_status'] === PaymentStatus::Paid->value
                    ? now()
                    : null;
            }

            if (array_key_exists('transaction_reference', $validated)) {
                $paymentData['transaction_reference'] = $validated['transaction_reference'];
            }

            $order->payment()->update($paymentData);
        }

        return new OrderResource($order->fresh()->load(['items', 'payment', 'telegramPaymentAlert']));
    }
}
