<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function store(StoreOrderRequest $request, OrderService $orders): JsonResponse
    {
        $order = $orders->create($request->validated());

        return (new OrderResource($order))
            ->additional([
                'message' => 'Order created. Wait for seller confirmation before paying.',
            ])
            ->response()
            ->setStatusCode(201);
    }
}
