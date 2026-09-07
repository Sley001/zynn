<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\PaymentQrUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelCheckoutSessionRequest;
use App\Http\Requests\ClaimCheckoutSessionPaidRequest;
use App\Http\Requests\StoreCheckoutSessionRequest;
use App\Http\Resources\CheckoutSessionResource;
use App\Models\CheckoutSession;
use App\Services\CheckoutSessionService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;

class CheckoutSessionController extends Controller
{
    public function store(
        StoreCheckoutSessionRequest $request,
        CheckoutSessionService $checkoutSessions,
    ): JsonResponse {
        try {
            $session = $checkoutSessions->create($request->validated());
        } catch (PaymentQrUnavailableException $exception) {
            return response()->json([
                'message' => 'No payment QR is available for this exact total. Please contact the seller.',
                'code' => 'payment_qr_unavailable',
                'total' => Money::centsToDecimal($exception->totalCents),
                'total_cents' => $exception->totalCents,
                'currency' => $exception->currency,
            ], 422);
        }

        return (new CheckoutSessionResource($session))
            ->additional(['message' => 'Checkout session created.'])
            ->response()
            ->setStatusCode(201);
    }

    public function status(
        CheckoutSession $checkoutSession,
        CheckoutSessionService $checkoutSessions,
    ): CheckoutSessionResource {
        return new CheckoutSessionResource($checkoutSessions->status($checkoutSession));
    }

    public function claimPaid(
        ClaimCheckoutSessionPaidRequest $request,
        CheckoutSession $checkoutSession,
        CheckoutSessionService $checkoutSessions,
    ): CheckoutSessionResource {
        return new CheckoutSessionResource($checkoutSessions->claimPaid(
            $checkoutSession,
            $request->validated('transaction_reference'),
            $request->file('receipt'),
        ));
    }

    public function cancel(
        CancelCheckoutSessionRequest $request,
        CheckoutSession $checkoutSession,
        CheckoutSessionService $checkoutSessions,
    ): CheckoutSessionResource {
        return new CheckoutSessionResource($checkoutSessions->cancel($checkoutSession));
    }
}
