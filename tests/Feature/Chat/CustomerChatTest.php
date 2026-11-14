<?php

use App\Livewire\ChatWidget;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(fn () => Queue::fake());

it('khách gửi tin → tạo thread lần đầu, tin hiển thị', function () {
    $u = User::factory()->create();

    Livewire::actingAs($u)->test(ChatWidget::class)
        ->call('toggle')
        ->set('body', 'Đơn #1 chờ lâu quá')
        ->call('send')
        ->assertHasNoErrors()
        ->assertSet('body', '')
        ->assertSee('Đơn #1 chờ lâu quá');

    expect(ChatThread::where('user_id', $u->id)->count())->toBe(1)->and(ChatMessage::sole()->sender_type)->toBe('customer');
});

it('2 lần tạo thread liên tiếp không lỗi unique', function () {
    $u = User::factory()->create();

    app(ChatService::class)->postCustomerMessage($u, 'a');
    app(ChatService::class)->postCustomerMessage($u, 'b');

    expect(ChatThread::count())->toBe(1)->and(ChatMessage::count())->toBe(2);
});

it('tin rỗng hoặc > 2.000 ký tự → lỗi', function (string $body) {
    Livewire::actingAs(User::factory()->create())->test(ChatWidget::class)
        ->set('body', $body)->call('send')->assertHasErrors('body');

    expect(ChatMessage::count())->toBe(0);
})->with(['', '   ', str_repeat('a', 2001)]);

it('tin thứ 11 trong 1 phút → lỗi', function () {
    $c = Livewire::actingAs(User::factory()->create())->test(ChatWidget::class);

    foreach (range(1, 10) as $i) {
        $c->set('body', "tin {$i}")->call('send')->assertHasNoErrors();
    }
    $c->set('body', 'tin 11')->call('send')->assertHasErrors('body');

    expect(ChatMessage::count())->toBe(10);
});

it('admin không thấy widget, khách thấy', function () {
    $this->actingAs(User::factory()->create())->get('/don-hang')->assertSee('Chat với admin');
    $this->actingAs(User::factory()->admin()->create())->get('/don-hang')->assertDontSee('Chat với admin');
});

it('refreshIfNew không có tin mới → không render lại', function () {
    $u = User::factory()->create();
    app(ChatService::class)->postCustomerMessage($u, 'xin chào');

    $c = Livewire::actingAs($u)->test(ChatWidget::class)->call('refreshIfNew');
    expect($c->effects['html'] ?? null)->toBeNull();

    app(ChatService::class)->postAdminMessage($u->chatThread->id, User::factory()->admin()->create(), 'Chào bạn');
    $c->call('refreshIfNew');
    expect($c->effects['html'] ?? null)->not->toBeNull();
});
