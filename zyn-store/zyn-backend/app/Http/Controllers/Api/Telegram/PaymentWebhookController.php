<?php

namespace App\Http\Controllers\Api\Telegram;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessTelegramPaymentUpdate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if (config('telegram.mode') !== 'webhook') {
            return response()->json(['ok' => false, 'message' => 'Telegram webhook mode is disabled.'], 409);
        }

        $secret = (string) config('telegram.webhook_secret');
        $providedSecret = (string) $request->header('X-Telegram-Bot-Api-Secret-Token');

        if ($secret === '' || ! hash_equals($secret, $providedSecret)) {
            return response()->json(['ok' => false], 403);
        }

        ProcessTelegramPaymentUpdate::dispatch($request->all());

        return response()->json(['ok' => true]);
    }
}
