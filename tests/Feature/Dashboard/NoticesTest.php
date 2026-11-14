<?php

use App\Filament\Resources\Notices\Pages\ManageNotices;
use App\Models\Notice;
use App\Models\User;
use Livewire\Livewire;

it('chỉ thông báo đang hiển thị, mới nhất trước, tối đa 5; HTML bị escape', function () {
    foreach (range(1, 6) as $i) {
        Notice::create(['title' => "TB {$i}", 'body' => 'nội dung', 'published_at' => now()->subDays(10 - $i)]);
    }
    Notice::create(['title' => 'Ẩn', 'body' => 'x', 'is_published' => false]);
    Notice::create(['title' => 'XSS', 'body' => '<b>đậm</b>', 'published_at' => now()->subDays(20)]);

    $res = $this->actingAs(User::factory()->create())->get('/')->assertOk();

    $res->assertSeeInOrder(['TB 6', 'TB 5', 'TB 4', 'TB 3', 'TB 2'])->assertDontSee('TB 1')->assertDontSee('Ẩn');
    expect(Notice::visible()->limit(5)->pluck('title')->all())->toBe(['TB 6', 'TB 5', 'TB 4', 'TB 3', 'TB 2']);
});

it('nội dung có thẻ HTML được escape', function () {
    Notice::create(['title' => 'XSS', 'body' => "<b>đậm</b>\ndòng 2"]);

    $html = $this->actingAs(User::factory()->create())->get('/')->getContent();
    expect($html)->toContain('&lt;b&gt;đậm&lt;/b&gt;<br />')->not->toContain('<b>đậm</b>');
});

it('admin CRUD thông báo', function () {
    $this->actingAs(User::factory()->admin()->create());
    filament()->setCurrentPanel('admin');

    Livewire::test(ManageNotices::class)
        ->callAction('create', ['title' => 'Bảo trì', 'body' => 'Tối nay 2h', 'published_at' => now()->format('Y-m-d H:i'), 'is_published' => true])
        ->assertHasNoActionErrors();

    expect(Notice::sole()->title)->toBe('Bảo trì');
});
