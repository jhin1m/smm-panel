<?php

namespace App\Exceptions;

use RuntimeException;

/** Gán giao dịch đã "Bỏ qua" cần xác nhận thêm. */
class ConfirmationRequired extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('exceptions.confirmation_required'));
    }
}
