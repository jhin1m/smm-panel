<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;

/** Đơn của chính khách — query luôn theo auth()->id(), không nhận user id từ client. */
class MyOrders extends Component
{
    use WithPagination;

    public function render()
    {
        return view('livewire.my-orders', [
            'orders' => auth()->user()->orders()->latest('id')->paginate(20),
        ])->title(__('orders.title'));
    }
}
