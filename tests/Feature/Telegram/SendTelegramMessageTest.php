<?php

use App\Jobs\SendTelegramMessage;
use App\Services\AdminNotifier;
use App\Settings\TelegramSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

function configureTelegram(bool $enabled = true, ?string $token = '123:SECRET-TOKEN', ?string $chat = '-100999'): void
{
    $s = app(TelegramSettings::class);
    $s->enabled = $enabled;
    $s->bot_token = $token;
    $s->chat_id = $chat;
    $s->save();
}

function runTelegramJob(string $html = 'hi'): SendTelegramMessage
{
    $job = new SendTelegramMessage($html);
    $fake = $job->withFakeQueueInteractions();
    $fake->handle(app(TelegramSettings::class));

    return $fake;
}

it('gửi tin với parse_mode HTML', function () {
    configureTelegram();
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

    runTelegramJob('<b>x</b>')->assertNotReleased()->assertNotFailed();

    Http::assertSent(fn ($r) => str_contains($r->url(), '/bot123:SECRET-TOKEN/sendMessage')
        && $r['chat_id'] === '-100999' && $r['parse_mode'] === 'HTML' && $r['text'] === '<b>x</b>');
});

it('429 → release theo retry_after', function () {
    configureTelegram();
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'parameters' => ['retry_after' => 7]], 429)]);

    runTelegramJob()->assertReleased(7);
});

it('400 → fail luôn, không retry', function () {
    configureTelegram();
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => false], 400)]);

    runTelegramJob()->assertFailed()->assertNotReleased();
});

it('5xx → ném lỗi để retry theo backoff', function () {
    configureTelegram();
    Http::fake(['api.telegram.org/*' => Http::response([], 502)]);

    expect(fn () => runTelegramJob())->toThrow(RuntimeException::class, 'Telegram 5xx: 502');
});

it('tắt hoặc thiếu token → không gọi HTTP', function (bool $enabled, ?string $token, ?string $chat) {
    configureTelegram($enabled, $token, $chat);
    Http::fake();

    runTelegramJob();

    Http::assertNothingSent();
})->with([
    'tắt' => [false, '1:abc', '-1'],
    'thiếu token' => [true, null, '-1'],
    'thiếu chat' => [true, '1:abc', null],
]);

it('lỗi kết nối không làm lộ token trong message', function () {
    configureTelegram();
    Http::fake(fn () => throw new ConnectionException('cURL error 28 for https://api.telegram.org/bot123:SECRET-TOKEN/sendMessage'));

    try {
        runTelegramJob();
        $this->fail('Phải ném exception');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->not->toContain('SECRET-TOKEN')->toContain('***');
    }
});

it('dispatch trong transaction bị rollback → không gửi; commit → gửi bản đã escape', function () {
    configureTelegram();
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);   // queue sync trong test

    try {
        DB::transaction(function () {
            app(AdminNotifier::class)->send('xin chào');
            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
    }

    Http::assertNothingSent();

    DB::transaction(fn () => app(AdminNotifier::class)->send('<script>'));
    Http::assertSent(fn ($r) => $r['text'] === '&lt;script&gt;');
});

it('bot_token lưu mã hoá trong bảng settings', function () {
    configureTelegram(token: '123:SECRET-TOKEN');

    $raw = DB::table('settings')->where('group', 'telegram')->where('name', 'bot_token')->value('payload');

    expect($raw)->not->toContain('SECRET-TOKEN')
        ->and(app(TelegramSettings::class)->bot_token)->toBe('123:SECRET-TOKEN');
});

it('lệnh app:notify-admin đưa job vào hàng đợi', function () {
    Queue::fake();

    $this->artisan('app:notify-admin', ['message' => 'Backup DB lỗi'])->assertSuccessful();

    Queue::assertPushed(SendTelegramMessage::class, fn ($job) => $job->html === 'Backup DB lỗi');
});
