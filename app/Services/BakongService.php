<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
 use Illuminate\Support\Str;

class BakongService
{
    private function token(): string
    {
        $token = (string) config('services.bakong.token');
        if (!$token) {
            throw new \RuntimeException('BAKONG_TOKEN is missing in .env');
        }
        return $token;
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.bakong.base_url'), '/');
    }

    private function generatePath(): string
    {
        return (string) config('services.bakong.generate_path', '/api/v1/khqr/generate');
    }

    private function checkPath(): string
    {
        return (string) config('services.bakong.check_path', '/api/v1/khqr/check');
    }

    private function generateMethod(): string
    {
        return strtoupper((string) config('services.bakong.generate_method', 'POST'));
    }

    private function checkMethod(): string
    {
        return strtoupper((string) config('services.bakong.check_method', 'POST'));
    }

    private function debugEnabled(): bool
    {
        return (bool) config('services.bakong.debug', false);
    }

    /**
     * Core request helper (keeps Response type so Intelephense is happy)
     */
    private function request(string $method, string $url, array $payload): array
    {
        if ($this->debugEnabled()) {
            Log::info('[Bakong] Request', [
                'method' => $method,
                'url' => $url,
                'payload' => $payload,
            ]);
        }

        /** @var Response $res */
        $res = Http::withToken($this->token())
            ->acceptJson()
            ->timeout(20)
            ->send($method, $url, [
                // If GET, payload should go to query
                $method === 'GET' ? 'query' : 'json' => $payload,
            ]);

        if ($this->debugEnabled()) {
            Log::info('[Bakong] Response', [
                'status' => $res->status(),
                'ok' => $res->ok(),
                'body' => $res->body(),
            ]);
        }

        return [
            'ok' => $res->ok(),
            'status' => $res->status(),
            'data' => $res->json(),
            'raw' => $res->body(),
        ];
    }

    /**
     * Generate KHQR
     * Return: ['ok'=>bool,'status'=>int,'data'=>array|null,'raw'=>string]
     */
    public function generateKhqr(array $payload): array
    {
        $url = $this->baseUrl() . $this->generatePath();
        return $this->request($this->generateMethod(), $url, $payload);
    }

    /**
     * Check KHQR status
     */
    public function checkKhqr(array $payload): array
    {
        $url = $this->baseUrl() . $this->checkPath();
        return $this->request($this->checkMethod(), $url, $payload);
    }

    /**
     * Helper: build payload for generate.
     * NOTE: You MUST adjust key names to match the official Bakong API docs you have.
     */
    public function buildGeneratePayload(array $data): array
    {
        return [
            'merchantName' => (string) config('services.bakong.merchant_name'),
            'merchantCity' => (string) config('services.bakong.merchant_city'),
            'accountId' => (string) config('services.bakong.account_id'),
            'mcc' => (string) config('services.bakong.mcc'),
            'countryCode' => (string) config('services.bakong.country'),
            'currency' => (int) config('services.bakong.currency'),
            'amount' => (float) ($data['amount'] ?? 0),
            'billNumber' => (string) ($data['billNumber'] ?? ''),
            'storeLabel' => (string) ($data['storeLabel'] ?? 'Bo Coffee'),
            'terminalLabel' => (string) ($data['table_no'] ?? ''),
            'purposeOfTransaction' => (string) ($data['purpose'] ?? 'Coffee order'),
            'qrExpireSeconds' => (int) config('services.bakong.qr_expire_seconds'),
        ];
    }

    /**
     * Helper: build payload for check.
     * Usually check needs md5/fullHash/trxId depending on response.
     */
    public function buildCheckPayload(array $data): array
    {
        return [
            'md5' => (string) ($data['md5'] ?? ''),
        ];
    }



public function localGenerateKhqr(array $data): array
{
    $amount = number_format((float) ($data['amount'] ?? 0), 2, '.', '');
    $merchantRef = (string) ($data['merchant_ref'] ?? ('ORD-' . Str::upper(Str::random(10))));
    $expiresAt = now()->addSeconds((int) config('services.bakong.qr_expire_seconds', 600));

    // NOTE: This is a placeholder QR string (NOT real KHQR spec)
    // It lets frontend show a QR while you finish real Bakong integration.
    $qrString = "KHQR|ACCOUNT=" . (string) config('services.bakong.account_id')
        . "|AMOUNT={$amount}"
        . "|REF={$merchantRef}"
        . "|CURRENCY=" . (string) config('services.bakong.currency');

    return [
        'qr_string' => $qrString,
        'md5' => md5($qrString),
        'full_hash' => hash('sha256', $qrString),
        'merchant_ref' => $merchantRef,
        'expires_at' => $expiresAt,
        'raw' => [
            'note' => 'Replace localGenerateKhqr() with real KHQR SDK generation output',
        ],
    ];
}

}
