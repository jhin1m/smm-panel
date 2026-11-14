<?php

use App\Filament\Pages\ChatInbox;
use App\Livewire\ChatWidget;
use App\Models\ChatMessage;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    Queue::fake();
    filament()->setCurrentPanel('admin');
    $this->admin = User::factory()->admin()->create();
});

it('admin chọn thread, trả lời; khách thấy tin admin', function () {
    $u = User::factory()->create();
    app(ChatService::class)->postCustomerMessage($u, 'Cần hỗ trợ');
    $threadId = $u->chatThread->id;

    Livewire::actingAs($this->admin)->test(ChatInbox::class, ['initialThread' => $threadId])
        ->assertSet('threadId', $threadId)
        ->assertSee('Cần hỗ trợ')
        ->set('body', 'Chào bạn, mình kiểm tra ngay')
        ->call('reply')
        ->assertHasNoErrors();

    expect(ChatMessage::where('sender_type', 'admin')->sole()->body)->toBe('Chào bạn, mình kiểm tra ngay')
        ->and(app(ChatService::class)->unreadCountForCustomer($u))->toBe(1);
});

it('script trong tin hiển thị escape ở ChatInbox và widget', function () {
    $u = User::factory()->create();
    app(ChatService::class)->postCustomerMessage($u, "<script>alert(1)</script>\ndòng 2");

    Livewire::actingAs($this->admin)->test(ChatInbox::class, ['initialThread' => $u->chatThread->id])
        ->assertDontSeeHtml('<script>alert(1)</script>')
        ->assertSeeHtml('&lt;script&gt;alert(1)&lt;/script&gt;<br />');

    Livewire::actingAs($u)->test(ChatWidget::class)->call('toggle')
        ->assertDontSeeHtml('<script>alert(1)</script>')
        ->assertSeeHtml('&lt;script&gt;');
});

it('trang hộp chat chỉ admin', function () {
    $this->actingAs(User::factory()->create())->get('/admin/chat-inbox')->assertForbidden();
    $this->actingAs($this->admin)->get('/admin/chat-inbox')->assertOk();
});

it('2 view chat không dùng {!! nào khác ngoài nl2br(e())', function () {
    foreach (['resources/views/livewire/chat-widget.blade.php', 'resources/views/filament/pages/chat-inbox.blade.php'] as $file) {
        preg_match_all('/\{!!(.*?)!!\}/s', file_get_contents(base_path($file)), $m);
        expect($m[1])->each->toMatch('/^\s*nl2br\(e\(\$m->body\)\)\s*$/');
    }
});
