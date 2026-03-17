<?php
// app/Services/BakongService.php
namespace App\Services;

use Illuminate\Support\Facades\Log;
use KHQR\BakongKHQR;
use KHQR\Models\IndividualInfo;

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

    private function debug(): bool
    {
        return (bool) config('services.bakong.debug', false);
    }

    private function merchantAccountId(): string
    {
        $id = (string) config('services.bakong.account_id');
        if (!$id) {
            throw new \RuntimeException('BAKONG_ACCOUNT_ID is missing in .env');
        }
        return $id;
    }

    private function currencyCode(): int
    {
        // USD=840, KHR=116
        return (int) config('services.bakong.currency', 840);
    }

    private function expireSeconds(): int
    {
        return (int) config('services.bakong.qr_expire_seconds', 300);
    }

    /**
     * Generate a REAL EMV KHQR string for USD with amount (dynamic).
     * Returns: qr_string, md5, expires_at, currency_code
     */
    public function generateKhqr(float $amount, string $merchantRef, ?string $tableNo = null): array
    {
        // Make sure amount is correct (2 decimals)
        $amount = (float) number_format($amount, 2, '.', '');
        $currency = $this->currencyCode();

        // Build IndividualInfo (supports optional fields too)
        $info = new IndividualInfo(
            bakongAccountID: $this->merchantAccountId(),
            merchantName: (string) config('services.bakong.merchant_name', 'STARCAFE'),
            merchantCity: (string) config('services.bakong.merchant_city', 'Phnom Penh'),
            currency: $currency,
            amount: $amount,
            billNumber: $merchantRef,                 // show reference in decode
            storeLabel: 'STARCAFE',
            terminalLabel: $tableNo ? ('T' . $tableNo) : null,
        );

        $resp = BakongKHQR::generateIndividual($info);

        // KHQRResponse has ->data (qr, md5)
        $qr = $resp->data['qr'] ?? null;
        $md5 = $resp->data['md5'] ?? null;

        if (!$qr || !$md5) {
            throw new \RuntimeException('KHQR generation failed: missing qr/md5');
        }

        // Optional: decode to confirm currency/amount (good debugging)
        if ($this->debug()) {
            $decoded = BakongKHQR::decode($qr);
            Log::info('[KHQR] generated', [
                'ref' => $merchantRef,
                'amount' => $amount,
                'currency' => $currency,
                'decoded_currency' => $decoded->data['transactionCurrency'] ?? null,
                'decoded_amount' => $decoded->data['transactionAmount'] ?? null,
            ]);
        }

        return [
            'qr_string' => $qr,
            'md5' => $md5,
            // Your db has bakong_full_hash; you can store the QR hash for reference (not required)
            'full_hash' => hash('sha256', $qr),
            'expires_at' => now()->addSeconds($this->expireSeconds()),
            'currency_code' => $currency,
            'amount' => $amount,
        ];
    }

    /**
     * Check transaction status by MD5 (Bakong Open API).
     * Returns array like: responseCode, responseMessage, data...
     */
    public function checkByMd5(string $md5, bool $isTest = false): array
    {
        $client = new BakongKHQR($this->token());
        $res = $client->checkTransactionByMD5($md5, $isTest);

        if ($this->debug()) {
            Log::info('[Bakong] checkByMd5', ['md5' => $md5, 'res' => $res]);
        }

        return $res;
    }

    /**
     * Decide paid or not from Bakong response
     */
    public function isPaidFromCheck(array $bakongResp, float $expectedAmount, string $expectedToAccountId): bool
    {
        if (($bakongResp['responseCode'] ?? 1) !== 0) return false;

        $data = $bakongResp['data'] ?? null;
        if (!$data) return false;

        // validate receiver + amount
        $to = (string) ($data['toAccountId'] ?? '');
        $amount = (float) ($data['amount'] ?? 0);

        $expectedAmount = (float) number_format($expectedAmount, 2, '.', '');

        if ($to !== $expectedToAccountId) return false;
        if (abs($amount - $expectedAmount) > 0.00001) return false;

        return true;
    }
}
