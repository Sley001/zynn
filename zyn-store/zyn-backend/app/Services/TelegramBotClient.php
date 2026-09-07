<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class TelegramBotClient
{
    public function isConfigured(): bool
    {
        return filled(config('telegram.bot_token'));
    }

    public function getUpdates(?int $offset = null, int $timeout = 25, int $limit = 20): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        $payload = [
            'timeout' => $timeout,
            'limit' => $limit,
            'allowed_updates' => ['message', 'channel_post', 'callback_query'],
        ];

        if ($offset !== null) {
            $payload['offset'] = $offset;
        }

        $response = $this->post('getUpdates', $payload, max(15, $timeout + 10));

        return $response->json('result', []);
    }

    public function sendMessage(int $chatId, string $text, ?array $replyMarkup = null): ?Response
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
        ];

        if ($replyMarkup) {
            $payload['reply_markup'] = $replyMarkup;
        }

        return $this->post('sendMessage', $payload);
    }

    public function answerCallbackQuery(string $callbackQueryId, string $text, bool $showAlert = false): ?Response
    {
        if (! $this->isConfigured()) {
            return null;
        }

        return $this->post('answerCallbackQuery', [
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
            'show_alert' => $showAlert,
        ]);
    }

    public function removeMessageReplyMarkup(int $chatId, int $messageId): ?Response
    {
        if (! $this->isConfigured()) {
            return null;
        }

        return $this->post('editMessageReplyMarkup', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ]);
    }

    private function post(string $method, array $payload, ?int $timeoutSeconds = null): Response
    {
        try {
            $response = Http::asJson()
                ->timeout($timeoutSeconds ?? 15)
                ->post($this->endpoint($method), $payload);
        } catch (\Throwable $exception) {
            throw new \RuntimeException($this->safeErrorMessage($exception->getMessage()), 0, $exception);
        }

        if ($response->failed()) {
            $description = (string) ($response->json('description') ?? 'No Telegram error description was returned.');

            throw new \RuntimeException("Telegram API HTTP {$response->status()}: {$description}");
        }

        return $response;
    }

    private function endpoint(string $method): string
    {
        return rtrim((string) config('telegram.api_base_url'), '/')
            .'/bot'.config('telegram.bot_token')
            .'/'.$method;
    }

    private function safeErrorMessage(string $message): string
    {
        $message = preg_replace('/bot[0-9]+:[A-Za-z0-9_-]+/', 'bot[hidden]', $message) ?? $message;

        return preg_replace('#/bot[^/\s]+/#', '/bot[hidden]/', $message) ?? $message;
    }
}
