<?php

use App\Filament\Pages\ChatInbox;
use App\Jobs\SendTelegramMessage;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

it('chỉ báo Telegram 1 lần cho chuỗi tin chưa đọc; poll không reset; chọn thread mới reset', function () {
    Queue::fake();
    filament()->setCurrentPanel('admin');
    $u = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $chat = app(ChatService::class);

    $chat->postCustomerMessage($u, 'tin 1');
    Queue::assertPushed(SendTelegramMessage::class, 1);

    $chat->postCustomerMessage($u, 'tin 2');
    Queue::assertPushed(SendTelegramMessage::class, 1);

    // Admin mở inbox chỉ để poll, không chọn thread → không reset cờ.
    Livewire::actingAs($admin)->test(ChatInbox::class)->call('refreshIfNew');
    $chat->postCustomerMessage($u, 'tin 3');
    Queue::assertPushed(SendTelegramMessage::class, 1);

    // Admin chọn thread → reset; tin kế tiếp báo lại.
    Livewire::actingAs($admin)->test(ChatInbox::class)->call('selectThread', $u->chatThread->id);
    $chat->postCustomerMessage($u, 'tin 4');
    Queue::assertPushed(SendTelegramMessage::class, 2);
});

it('nội dung gửi Telegram được escape', function () {
    Queue::fake();
    $u = User::factory()->create(['name' => '<b>Hacker</b>']);

    app(ChatService::class)->postCustomerMessage($u, '<script>alert(1)</script>');

    Queue::assertPushed(SendTelegramMessage::class, fn ($job) => str_contains($job->html, '&lt;script&gt;')
        && str_contains($job->html, '&lt;b&gt;Hacker') && ! str_contains($job->html, '<script>'));
});
