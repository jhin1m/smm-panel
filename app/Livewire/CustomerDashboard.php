<?php

namespace App\Livewire;

use App\Enums\Tier;
use App\Models\Notice;
use App\Services\TierService;
use App\Settings\TierSettings;
use Livewire\Component;

class CustomerDashboard extends Component
{
    public function render()
    {
        $user = auth()->user()->fresh();
        $settings = app(TierSettings::class);

        return view('livewire.customer-dashboard', [
            'user' => $user,
            'isAgency' => $user->tier === Tier::Agency,
            'remainingToAgency' => max(0, $settings->agency_threshold - $user->total_deposited),
            'discount' => app(TierService::class)->discountPercent($user),
            'agencyDiscount' => $settings->agency_discount_percent,
            'activeOrders' => $user->orders()->active()->count(),
            'totalOrders' => $user->orders()->count(),
            'recentOrders' => $user->orders()->latest('id')->limit(4)->get(),
            'notices' => Notice::visible()->limit(5)->get(),
        ])->title(__('dashboard.title'));
    }
}
