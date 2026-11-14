<?php

use App\Filament\Resources\Categories\Pages\ManageCategories;
use App\Filament\Resources\Services\Pages\ManageServices;
use App\Models\Category;
use App\Models\Service;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
    filament()->setCurrentPanel('admin');
});

it('tạo danh mục + dịch vụ', function () {
    Livewire::test(ManageCategories::class)
        ->callAction('create', ['name' => 'Facebook', 'slug' => 'facebook', 'badge' => 'Fb', 'color' => '#1877f2', 'sort_order' => 1, 'is_active' => true])
        ->assertHasNoActionErrors();
    $cat = Category::sole();

    Livewire::test(ManageServices::class)
        ->callAction('create', [
            'category_id' => $cat->id, 'name' => 'Like', 'unit' => 'per_1000', 'price' => 16098,
            'min_quantity' => 50, 'max_quantity' => 50000, 'speed' => '10.000/ngày', 'start_time' => '5 phút', 'is_active' => true, 'sort_order' => 0,
        ])
        ->assertHasNoActionErrors();

    expect(Service::sole()->only(['price', 'speed', 'start_time']))->toBe(['price' => 16098, 'speed' => '10.000/ngày', 'start_time' => '5 phút']);
});

it('max < min → lỗi', function () {
    $cat = Category::factory()->create();

    Livewire::test(ManageServices::class)
        ->callAction('create', ['category_id' => $cat->id, 'name' => 'X', 'unit' => 'per_1000', 'price' => 1, 'min_quantity' => 100, 'max_quantity' => 50])
        ->assertHasActionErrors(['max_quantity']);
});

it('dịch vụ đã có đơn không xoá được', function () {
    $s = Service::factory()->create();
    placeOrder(userWithBalance(100_000), $s);

    Livewire::test(ManageServices::class)->assertActionHidden(TestAction::make('delete')->table($s));
});
