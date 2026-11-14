<?php

namespace App\Livewire;

use App\Exceptions\InsufficientBalance;
use App\Models\Category;
use App\Models\Service;
use App\Services\OrderService;
use App\Services\PricingService;
use App\Services\TierService;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Màn Đặt đơn. categoryId/serviceId là input KHÔNG tin cậy từ client: luôn tra lại điều kiện đang bán ở server.
 */
class PlaceOrder extends Component
{
    public ?int $categoryId = null;

    public ?int $serviceId = null;

    public string $link = '';

    public $quantity = null;

    public string $note = '';

    public function mount(): void
    {
        $first = $this->categories->first();
        if ($first) {
            $this->selectCategory($first->id);
        }
    }

    /** Danh mục đang bật và có ít nhất 1 dịch vụ đang bán. */
    #[Computed]
    public function categories(): Collection
    {
        return Category::active()
            ->whereHas('services', fn ($q) => $q->where('is_active', true))
            ->orderBy('sort_order')->orderBy('id')->get();
    }

    #[Computed]
    public function services(): Collection
    {
        if (! $this->categoryId) {
            return new Collection;
        }

        return Service::orderable()->where('category_id', $this->categoryId)->orderBy('sort_order')->orderBy('id')->get();
    }

    #[Computed]
    public function service(): ?Service
    {
        return $this->serviceId ? Service::orderable()->find($this->serviceId) : null;
    }

    #[Computed]
    public function discountPercent(): int
    {
        return app(TierService::class)->discountPercent(auth()->user());
    }

    /** Xem trước giá — cùng PricingService với lúc tạo đơn. */
    #[Computed]
    public function total(): int
    {
        $s = $this->service;
        $qty = filter_var($this->quantity, FILTER_VALIDATE_INT);

        if (! $s || $qty === false || $qty < 1 || $qty > 10_000_000) {
            return 0;
        }

        return app(PricingService::class)->total($s->price, $s->unit, $qty, $this->discountPercent);
    }

    public function selectCategory(int $id): void
    {
        if (! Category::active()->whereKey($id)->exists()) {
            return;
        }

        $this->categoryId = $id;
        unset($this->services);
        $first = $this->services->first();
        $first ? $this->selectService($first->id) : $this->serviceId = null;
    }

    public function selectService(int $id): void
    {
        $service = Service::orderable()->find($id);
        if (! $service) {
            $this->addError('service', __('orders.errors.service_unavailable'));

            return;
        }

        $this->resetErrorBag('service');
        $this->serviceId = $service->id;
        $this->categoryId = $service->category_id;
        $qty = filter_var($this->quantity, FILTER_VALIDATE_INT);
        if ($qty === false || $qty < $service->min_quantity || $qty > $service->max_quantity) {
            $this->quantity = max($service->min_quantity, min(1000, $service->max_quantity));
        }
        unset($this->service);
    }

    public function submit(OrderService $orders)
    {
        if (! $this->serviceId) {
            $this->addError('service', __('orders.place.pick_service'));

            return null;
        }

        try {
            $order = $orders->place(auth()->user(), $this->serviceId, [
                'link' => $this->link,
                'quantity' => $this->quantity,
                'note' => $this->note,
            ]);
        } catch (InsufficientBalance $e) {
            $this->addError('balance', $e->getMessage());

            return null;
        }

        session()->flash('toast', __('orders.place.created', ['id' => $order->id]));

        return $this->redirectRoute('orders.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.place-order', ['balance' => (int) auth()->user()->fresh()->balance])->title(__('orders.place.title'));
    }
}
