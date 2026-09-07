<?php

namespace App\Services;

use App\Enums\CheckoutSessionStatus;
use App\Exceptions\PaymentQrUnavailableException;
use App\Models\CheckoutSession;
use App\Models\PaymentQr;
use App\Models\TelegramPaymentAlert;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class CheckoutSessionService
{
    public function __construct(
        private readonly CartPricingService $pricing,
        private readonly StoreSettingsService $settings,
        private readonly TelegramPaymentAlertService $telegramAlerts,
    ) {}

    public function create(array $data): CheckoutSession
    {
        return DB::transaction(function () use ($data): CheckoutSession {
            $quote = $this->pricing->quote($data['items'], lockProducts: true);
            $paymentQr = PaymentQr::query()
                ->where('amount_cents', $quote['total_cents'])
                ->where('currency', $quote['currency'])
                ->where('is_active', true)
                ->latest()
                ->first();

            if (! $paymentQr && ($quote['currency'] !== 'USD' || ! config('store.payment_link'))) {
                throw new PaymentQrUnavailableException(
                    'No active payment QR is available for this exact total.',
                    $quote['total_cents'],
                    $quote['currency'],
                );
            }

            return CheckoutSession::query()->create([
                'token' => (string) Str::uuid(),
                'customer_snapshot' => [
                    'name' => $data['customer_name'],
                    'phone' => $data['phone'],
                    'province' => $data['province'],
                    'district' => $data['district'],
                    'address_note' => $data['address_note'] ?? null,
                    'telegram_username' => $data['telegram_username'] ?? null,
                    'age_confirmed_at' => now()->toISOString(),
                ],
                'cart_snapshot' => [
                    'items' => $quote['items'],
                ],
                'subtotal_cents' => $quote['subtotal_cents'],
                'delivery_fee_cents' => $quote['delivery_fee_cents'],
                'total_cents' => $quote['total_cents'],
                'currency' => $quote['currency'],
                'payment_method' => $data['payment_method'],
                'status' => CheckoutSessionStatus::AwaitingPayment,
                'payment_qr_id' => $paymentQr?->id,
                'qr_image_path' => $paymentQr?->image_path ?? '',
                'expires_at' => now()->addMinutes($this->settings->checkoutSessionLifetimeMinutes()),
            ]);
        }, 3);
    }

    public function status(CheckoutSession $session): CheckoutSession
    {
        return $this->expireIfNeeded($session)
            ->loadMissing(['resultingOrder.items', 'resultingOrder.payment', 'telegramPaymentAlert']);
    }

    public function claimPaid(CheckoutSession $session, string $reference, ?UploadedFile $receipt = null): CheckoutSession
    {
        $receiptPath = null;

        try {
            $result = DB::transaction(function () use ($session, $reference, $receipt, &$receiptPath): CheckoutSession {
                $lockedSession = CheckoutSession::query()
                    ->whereKey($session->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $lockedSession = $this->expireIfNeeded($lockedSession);

                if ($lockedSession->status === CheckoutSessionStatus::AwaitingPayment) {
                    if (! $receipt) {
                        throw ValidationException::withMessages(['receipt' => 'Upload a receipt photo before submitting your payment.']);
                    }

                    // Reuse the same private file if the database retries a deadlock.
                    // A retry after an accepted claim never replaces its evidence.
                    if ($receiptPath === null) {
                        $receiptPath = 'payment-receipts/'.Str::uuid().'.'.$receipt->guessExtension();
                        if (! Storage::disk('local')->putFileAs('', $receipt, $receiptPath, ['visibility' => 'private'])) {
                            throw new RuntimeException('The receipt could not be saved. Please try again.');
                        }
                    }

                    $lockedSession->update([
                        'status' => CheckoutSessionStatus::PaymentClaimed,
                        'payment_claimed_at' => now(),
                        'claimed_transaction_reference' => strtoupper(trim($reference)),
                        'payment_receipt_path' => $receiptPath,
                    ]);
                }

                return $lockedSession->fresh();
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            if ($receiptPath !== null) {
                Storage::disk('local')->delete($receiptPath);
            }

            throw ValidationException::withMessages([
                'transaction_reference' => 'This transaction ID is already attached to another payment claim.',
            ]);
        } catch (Throwable $exception) {
            if ($receiptPath !== null) {
                Storage::disk('local')->delete($receiptPath);
            }

            throw $exception;
        }

        if ($receiptPath !== null && $result->payment_receipt_path !== $receiptPath) {
            Storage::disk('local')->delete($receiptPath);
        }

        if ($result->status === CheckoutSessionStatus::PaymentClaimed) {
            $alert = TelegramPaymentAlert::query()
                ->whereRaw('UPPER(transaction_id) = ?', [strtoupper(trim($reference))])
                ->whereNull('order_id')
                ->first();

            if ($alert) {
                $this->telegramAlerts->matchExistingAlert($alert);

                return $this->status($result->fresh());
            }
        }

        return $result;
    }

    public function cancel(CheckoutSession $session): CheckoutSession
    {
        return DB::transaction(function () use ($session): CheckoutSession {
            $lockedSession = CheckoutSession::query()
                ->whereKey($session->id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedSession = $this->expireIfNeeded($lockedSession);

            if ($lockedSession->status === CheckoutSessionStatus::PaymentClaimed) {
                throw ValidationException::withMessages(['status' => 'A submitted payment claim must be reviewed by the seller.']);
            }

            if (in_array($lockedSession->status, [
                CheckoutSessionStatus::AwaitingPayment,
                CheckoutSessionStatus::PaymentClaimed,
            ], true)) {
                $lockedSession->update(['status' => CheckoutSessionStatus::Cancelled]);
            }

            return $lockedSession->fresh();
        }, 3);
    }

    public function expireAndPrune(): array
    {
        $expired = CheckoutSession::query()
            ->whereIn('status', [
                CheckoutSessionStatus::AwaitingPayment->value,
            ])
            ->where('expires_at', '<=', now())
            ->update(['status' => CheckoutSessionStatus::Expired->value]);

        $prunableSessions = CheckoutSession::query()
            ->where('status', CheckoutSessionStatus::Expired->value)
            ->whereNull('resulting_order_id')
            ->where('updated_at', '<=', now()->subMinutes($this->settings->checkoutSessionPruneAfterMinutes()))
            ->get(['id', 'qr_image_path']);

        $pruned = CheckoutSession::query()
            ->whereKey($prunableSessions->pluck('id'))
            ->delete();

        $prunableSessions
            ->pluck('qr_image_path')
            ->filter()
            ->unique()
            ->each(fn (string $path) => $this->deleteQrImageIfUnused($path));

        return [
            'expired' => $expired,
            'pruned' => $pruned,
        ];
    }

    private function expireIfNeeded(CheckoutSession $session): CheckoutSession
    {
        if (
            in_array($session->status, [
                CheckoutSessionStatus::AwaitingPayment,
            ], true)
            && $session->expires_at->isPast()
        ) {
            $session->update(['status' => CheckoutSessionStatus::Expired]);

            return $session->fresh();
        }

        return $session;
    }

    private function deleteQrImageIfUnused(string $path): void
    {
        $usedByQr = PaymentQr::query()->where('image_path', $path)->exists();
        $usedByActiveCheckout = CheckoutSession::query()
            ->where('qr_image_path', $path)
            ->whereIn('status', [
                CheckoutSessionStatus::AwaitingPayment->value,
                CheckoutSessionStatus::PaymentClaimed->value,
            ])
            ->where('expires_at', '>', now())
            ->exists();

        if (! $usedByQr && ! $usedByActiveCheckout) {
            Storage::disk('public')->delete($path);
        }
    }
}
