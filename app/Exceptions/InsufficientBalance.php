<?php

namespace App\Exceptions;

use App\Support\Money;
use RuntimeException;

class InsufficientBalance extends RuntimeException
{
    public function __construct(public readonly int $balance = 0, public readonly int $required = 0)
    {
        parent::__construct(__('exceptions.insufficient_balance', ['balance' => Money::vnd($balance), 'required' => Money::vnd($required)]));
    }
}
