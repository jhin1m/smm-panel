<?php

namespace App\Exceptions;

use RuntimeException;

class DepositAlreadyProcessed extends RuntimeException
{
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? __('exceptions.deposit_already_processed'));
    }
}
