<?php

namespace App\Exceptions;

use RuntimeException;

class SelfAdjustmentForbidden extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('exceptions.self_adjustment_forbidden'));
    }
}
