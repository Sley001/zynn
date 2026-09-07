<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\CheckoutSessionStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CheckoutSessionResource;
use App\Models\CheckoutSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CheckoutPaymentController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->is_admin, 403);
        $sessions = CheckoutSession::query()
            ->where('status', CheckoutSessionStatus::PaymentClaimed->value)
            ->whereNull('consumed_at')->oldest('payment_claimed_at')->paginate(50);

        return response()->json([
            'data' => $sessions->map(fn (CheckoutSession $session): array => [
                'token' => $session->token,
                'customer' => $session->customer_snapshot,
                'items' => $session->cart_snapshot['items'] ?? [],
                'total_cents' => $session->total_cents,
                'currency' => $session->currency,
                'has_receipt' => (bool) $session->payment_receipt_path,
                'payment_claimed_at' => $session->payment_claimed_at?->toISOString(),
            ]),
            'meta' => ['current_page' => $sessions->currentPage(), 'last_page' => $sessions->lastPage(), 'total' => $sessions->total()],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function receipt(Request $request, CheckoutSession $checkoutSession): StreamedResponse
    {
        abort_unless($request->user()?->is_admin, 403);
        $path = $checkoutSession->payment_receipt_path;
        $disk = Storage::disk('local');
        abort_unless($path && $disk->exists($path), 404);
        $mimeType = $disk->mimeType($path);
        abort_unless(in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true), 404);

        return $disk->response($path, 'payment-receipt.'.pathinfo($path, PATHINFO_EXTENSION), [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ], 'inline');
    }

    public function reject(Request $request, CheckoutSession $checkoutSession): CheckoutSessionResource
    {
        abort_unless($request->user()?->is_admin, 403);
        $data = $request->validate(['reason' => ['required', 'string', 'min:6', 'max:1000']]);
        $session = DB::transaction(function () use ($request, $checkoutSession, $data): CheckoutSession {
            $session = CheckoutSession::query()->whereKey($checkoutSession->id)->lockForUpdate()->firstOrFail();
            abort_unless($session->status === CheckoutSessionStatus::PaymentClaimed && ! $session->consumed_at, 422, 'Only a pending claim can be rejected.');
            $session->update([
                'status' => CheckoutSessionStatus::Cancelled,
                'customer_snapshot' => array_merge($session->customer_snapshot, ['payment_review' => [
                    'rejected_by' => $request->user()->id, 'reason' => $data['reason'], 'reviewed_at' => now()->toISOString(),
                ]]),
            ]);

            return $session;
        }, 3);

        return new CheckoutSessionResource($session);
    }
}
