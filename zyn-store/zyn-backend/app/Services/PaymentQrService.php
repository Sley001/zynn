<?php

namespace App\Services;

use App\Enums\CheckoutSessionStatus;
use App\Models\CheckoutSession;
use App\Models\PaymentQr;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PaymentQrService
{
    public function create(array $data): PaymentQr
    {
        $amountCents = Money::decimalToCents((string) $data['amount']);
        $currency = Money::normalizeCurrency((string) $data['currency']);
        $isActive = (bool) ($data['is_active'] ?? true);
        $imagePath = $this->storeImage($data['image']);

        try {
            return DB::transaction(function () use ($data, $amountCents, $currency, $isActive, $imagePath): PaymentQr {
                if ($isActive) {
                    $this->deactivateMatchingQrs($amountCents, $currency);
                }

                return PaymentQr::query()->create([
                    'amount' => Money::centsToDecimal($amountCents),
                    'amount_cents' => $amountCents,
                    'currency' => $currency,
                    'image_path' => $imagePath,
                    'is_active' => $isActive,
                    'admin_note' => $this->cleanNote($data['admin_note'] ?? null),
                ]);
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($imagePath);
            throw $exception;
        }
    }

    public function update(PaymentQr $paymentQr, array $data): PaymentQr
    {
        $oldImagePath = $paymentQr->image_path;
        $newImagePath = isset($data['image']) ? $this->storeImage($data['image']) : null;

        try {
            $updated = DB::transaction(function () use ($paymentQr, $data, $newImagePath): PaymentQr {
                $amountCents = array_key_exists('amount', $data)
                    ? Money::decimalToCents((string) $data['amount'])
                    : $paymentQr->amount_cents;
                $currency = array_key_exists('currency', $data)
                    ? Money::normalizeCurrency((string) $data['currency'])
                    : $paymentQr->currency;
                $isActive = array_key_exists('is_active', $data)
                    ? (bool) $data['is_active']
                    : $paymentQr->is_active;

                if ($isActive) {
                    $this->deactivateMatchingQrs($amountCents, $currency, $paymentQr->id);
                }

                $paymentQr->update([
                    'amount' => Money::centsToDecimal($amountCents),
                    'amount_cents' => $amountCents,
                    'currency' => $currency,
                    'image_path' => $newImagePath ?? $paymentQr->image_path,
                    'is_active' => $isActive,
                    'admin_note' => array_key_exists('admin_note', $data)
                        ? $this->cleanNote($data['admin_note'])
                        : $paymentQr->admin_note,
                ]);

                return $paymentQr->fresh();
            });
        } catch (\Throwable $exception) {
            if ($newImagePath) {
                Storage::disk('public')->delete($newImagePath);
            }
            throw $exception;
        }

        if ($newImagePath) {
            $this->deleteImageIfUnused($oldImagePath);
        }

        return $updated;
    }

    public function delete(PaymentQr $paymentQr): void
    {
        $imagePath = $paymentQr->image_path;
        $paymentQr->delete();

        $this->deleteImageIfUnused($imagePath);
    }

    private function storeImage(UploadedFile $image): string
    {
        return $image->store('payment-qrs', 'public');
    }

    private function cleanNote(?string $note): ?string
    {
        $cleanNote = trim((string) $note);

        return $cleanNote === '' ? null : $cleanNote;
    }

    private function deactivateMatchingQrs(int $amountCents, string $currency, ?int $exceptId = null): void
    {
        PaymentQr::query()
            ->where('amount_cents', $amountCents)
            ->where('currency', $currency)
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->update(['is_active' => false]);
    }

    private function deleteImageIfUnused(?string $imagePath): void
    {
        if (! $imagePath) {
            return;
        }

        $usedByQr = PaymentQr::query()->where('image_path', $imagePath)->exists();
        $usedByActiveCheckout = CheckoutSession::query()
            ->where('qr_image_path', $imagePath)
            ->whereIn('status', [
                CheckoutSessionStatus::AwaitingPayment->value,
                CheckoutSessionStatus::PaymentClaimed->value,
            ])
            ->where('expires_at', '>', now())
            ->exists();

        if (! $usedByQr && ! $usedByActiveCheckout) {
            Storage::disk('public')->delete($imagePath);
        }
    }
}
