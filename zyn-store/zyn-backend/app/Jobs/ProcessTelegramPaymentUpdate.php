<?php

namespace App\Jobs;

use App\Services\TelegramPaymentAlertService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessTelegramPaymentUpdate implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly array $update) {}

    public function handle(TelegramPaymentAlertService $alerts): void
    {
        $alerts->processUpdate($this->update);
    }
}
