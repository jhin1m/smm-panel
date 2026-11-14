<?php

use App\Jobs\SendTelegramMessage;
use App\Models\BankTransaction;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config(['sepay.webhook_secret' => SEPAY_TEST_SECRET]);
    configureDeposit();
});

it('chữ ký sai → 401, không lưu', function () {
    postSepay(sepayPayload(), secret: 'wrong-secret')->assertStatus(401);

    expect(BankTransaction::count())->toBe(0);
});

it('timestamp lệch 6 phút → 401', function () {
    postSepay(sepayPayload(), timestamp: time() - 360)->assertStatus(401);
    postSepay(sepayPayload(), timestamp: time() + 360)->assertStatus(401);

    expect(BankTransaction::count())->toBe(0);
});

it('thiếu secret → 503 + log critical', function () {
    config(['sepay.webhook_secret' => null]);
    Log::spy();

    postSepay(sepayPayload())->assertStatus(503);

    Log::shouldHaveReceived('critical')->once();
    expect(BankTransaction::count())->toBe(0);
});

it('header Authorization: Apikey không có tác dụng', function () {
    $body = json_encode(sepayPayload());

    $this->call('POST', '/webhooks/sepay', [], [], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Apikey '.SEPAY_TEST_SECRET,
    ], $body)->assertStatus(401);
});

it('10 lần 401 trong 10 phút → 1 job Telegram', function () {
    Queue::fake();

    foreach (range(1, 12) as $_) {
        postSepay(sepayPayload(), secret: 'bad')->assertStatus(401);
    }

    Queue::assertPushed(SendTelegramMessage::class, 1);
});

it('IP ngoài allowlist + chữ ký đúng → vẫn credited + log cảnh báo', function () {
    $u = User::factory()->create();
    Log::spy();

    postSepay(sepayPayload(['code' => $u->deposit_code]), headers: ['REMOTE_ADDR' => '8.8.8.8'])->assertOk();

    expect(BankTransaction::sole()->status)->toBe('credited');
    Log::shouldHaveReceived('warning')->withArgs(fn ($msg) => str_contains($msg, 'allowlist'))->once();
});

it('webhook không dùng session/CSRF', function () {
    postSepay(sepayPayload())->assertOk()->assertCookieMissing(config('session.cookie'));
});
