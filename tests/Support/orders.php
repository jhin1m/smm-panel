<?php

use App\Enums\TxType;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Services\OrderService;
use App\Services\WalletService;

/** User có sẵn số dư (qua sổ cái, không set thẳng balance). */
function userWithBalance(int $balance, array $attrs = []): User
{
    $u = User::factory()->create($attrs);
    if ($balance > 0) {
        app(WalletService::class)->credit($u, $balance, TxType::Topup, "seed:{$u->id}:".uniqid());
    }

    return $u->fresh();
}

function placeOrder(User $u, Service $s, int $qty = 1000, string $link = 'https://facebook.com/p/1'): Order
{
    return app(OrderService::class)->place($u, $s->id, ['link' => $link, 'quantity' => $qty, 'note' => null]);
}
