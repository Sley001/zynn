<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentQrRequest;
use App\Http\Requests\UpdatePaymentQrRequest;
use App\Http\Resources\PaymentQrResource;
use App\Models\PaymentQr;
use App\Services\PaymentQrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class PaymentQrController extends Controller
{
    public function index()
    {
        $paymentQrs = PaymentQr::query()
            ->orderByDesc('is_active')
            ->orderBy('currency')
            ->orderBy('amount_cents')
            ->orderByDesc('id')
            ->get();

        return PaymentQrResource::collection($paymentQrs);
    }

    public function store(StorePaymentQrRequest $request, PaymentQrService $paymentQrs): JsonResponse
    {
        return (new PaymentQrResource($paymentQrs->create($request->validated())))
            ->response()
            ->setStatusCode(201);
    }

    public function show(PaymentQr $paymentQr): PaymentQrResource
    {
        return new PaymentQrResource($paymentQr);
    }

    public function update(
        UpdatePaymentQrRequest $request,
        PaymentQr $paymentQr,
        PaymentQrService $paymentQrs,
    ): PaymentQrResource {
        return new PaymentQrResource($paymentQrs->update($paymentQr, $request->validated()));
    }

    public function destroy(PaymentQr $paymentQr, PaymentQrService $paymentQrs): Response
    {
        $paymentQrs->delete($paymentQr);

        return response()->noContent();
    }
}
