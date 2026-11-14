<?php

use App\Settings\DepositSettings;
use Illuminate\Testing\TestResponse;

const SEPAY_TEST_SECRET = 'test-webhook-secret';

function sepayPayload(array $overrides = []): array
{
    static $seq = 90000;

    return array_merge([
        'id' => ++$seq,
        'gateway' => 'MBBank',
        'transactionDate' => now()->format('Y-m-d H:i:s'),
        'accountNumber' => '0123456789',
        'subAccount' => null,
        'code' => null,
        'content' => 'chuyen tien',
        'transferType' => 'in',
        'transferAmount' => 100_000,
        'accumulated' => 5_000_000,
        'referenceCode' => 'FT'.$seq,
        'description' => 'BankAPINotify',
    ], $overrides);
}

/** POST webhook đã ký HMAC như SePay. */
function postSepay(array $payload, ?string $secret = SEPAY_TEST_SECRET, ?int $timestamp = null, array $headers = []): TestResponse
{
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $ts = (string) ($timestamp ?? time());
    $sig = $secret === null ? 'sha256=bad' : 'sha256='.hash_hmac('sha256', $ts.'.'.$body, $secret);

    return test()->call('POST', '/webhooks/sepay', [], [], [], array_merge([
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_SEPAY_TIMESTAMP' => $ts,
        'HTTP_X_SEPAY_SIGNATURE' => $sig,
        'REMOTE_ADDR' => '172.236.138.20',
    ], $headers), $body);
}

function configureDeposit(array $overrides = []): DepositSettings
{
    $s = app(DepositSettings::class);
    foreach (array_merge([
        'bank_bin' => '970422', 'bank_name' => 'MB Bank', 'account_number' => '0123456789',
        'account_name' => 'NGUYEN VAN A', 'bonus_rules' => [],
    ], $overrides) as $k => $v) {
        $s->{$k} = $v;
    }
    $s->save();

    return $s;
}
