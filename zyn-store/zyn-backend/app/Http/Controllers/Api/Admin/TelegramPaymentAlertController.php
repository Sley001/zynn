<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\TelegramPaymentAlertStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\TelegramPaymentAlertResource;
use App\Models\TelegramPaymentAlert;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TelegramPaymentAlertController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::enum(TelegramPaymentAlertStatus::class)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $perPage = min(max((int) ($validated['per_page'] ?? 50), 1), 100);

        $alerts = TelegramPaymentAlert::query()
            ->with('order')
            ->when(
                isset($validated['status']),
                fn ($query) => $query->where('status', $validated['status']),
                fn ($query) => $query->whereIn('status', [
                    TelegramPaymentAlertStatus::Unmatched->value,
                    TelegramPaymentAlertStatus::NeedsReview->value,
                    TelegramPaymentAlertStatus::Rejected->value,
                    TelegramPaymentAlertStatus::Duplicate->value,
                ]),
            )
            ->latest()
            ->paginate($perPage);

        return TelegramPaymentAlertResource::collection($alerts);
    }
}
