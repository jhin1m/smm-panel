<?php

namespace App\Exceptions;

use App\Enums\OrderStatus;
use RuntimeException;

class InvalidOrderTransition extends RuntimeException
{
    public function __construct(OrderStatus $from, OrderStatus $to)
    {
        parent::__construct(__('exceptions.invalid_order_transition', ['from' => $from->label(), 'to' => $to->label()]));
    }
}
