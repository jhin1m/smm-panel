<?php

use App\Filament\Pages\ChatInbox;
use App\Livewire\ChatWidget;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(fn () => Queue::fake());

it('badge = ChatInbox = unreadThreadsForAdmin; tin admin không tính; chọn thread → về 0', function () {
    filament()->setCurrentPanel('admin');
    $chat = app(ChatService::class);
    $admin = User::factory()->admin()->create();
    [$a, $b, $c] = User::factory()->count(3)->create();

    $chat->postCustomerMessage($a, 'a1');
    $chat->postCustomerMessage($a, 'a2');
    $chat->postCustomerMessage($b, 'b1');
    $chat->postCustomerMessage($c, 'c1');
    $chat->postAdminMessage($c->chatThread->id, $admin, 'trả lời c');   // c đã được trả lời → đọc

    expect($chat->unreadThreadsForAdmin())->toBe(2)
        ->and(ChatInbox::getNavigationBadge())->toBe('2')
        ->and($chat->unreadCountsForAdmin())->toBe([$a->chatThread->id => 2, $b->chatThread->id => 1])
        ->and($chat->unreadCountForAdmin($a->chatThread->id))->toBe(2);

    Livewire::actingAs($admin)->test(ChatInbox::class)->call('selectThread', $a->chatThread->id);

    expect($chat->unreadThreadsForAdmin())->toBe(1);
});

it('phía khách: tin admin chưa đọc → badge; mở widget → về 0', function () {
    $chat = app(ChatService::class);
    $u = User::factory()->create();
    $chat->postCustomerMessage($u, 'hỏi');
    $chat->postAdminMessage($u->chatThread->id, User::factory()->admin()->create(), 'đáp');

    expect($chat->unreadCountForCustomer($u))->toBe(1);
    Livewire::actingAs($u)->test(ChatWidget::class)->assertSee('class="badge"', false)->call('toggle');
    expect($chat->unreadCountForCustomer($u->fresh()))->toBe(0);
});
