<?php

use App\Filament\Pages\ChatInbox;
use App\Livewire\ChatWidget;
use App\Models\ChatMessage;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(fn () => Queue::fake());

it('khách A không đọc/gửi được vào thread của B', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    app(ChatService::class)->postCustomerMessage($b, 'bí mật của B');

    $c = Livewire::actingAs($a)->test(ChatWidget::class)->call('toggle')->assertDontSee('bí mật của B');

    // Widget không có thuộc tính thread id; lastSeenId bị khoá.
    expect(fn () => $c->set('lastSeenId', 999))->toThrow(Exception::class);
    $c->set('body', 'tin của A')->call('send');

    expect(ChatMessage::where('body', 'tin của A')->sole()->thread->user_id)->toBe($a->id);
});

it('admin không set được threadId từ client; chọn thread không tồn tại → 404', function () {
    filament()->setCurrentPanel('admin');
    $admin = User::factory()->admin()->create();

    $c = Livewire::actingAs($admin)->test(ChatInbox::class);
    expect(fn () => $c->set('threadId', 1))->toThrow(Exception::class);
    $c->call('selectThread', 999999)->assertStatus(404);
    expect($c->get('threadId'))->toBeNull();
});
