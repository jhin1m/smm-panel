<?php

namespace App\Exceptions;

use RuntimeException;

/** Cùng idempotency key nhưng khác user/số tiền → lỗi lập trình hoặc dữ liệu giả. */
class IdempotencyConflict extends RuntimeException
{
    public function __construct(string $key = '')
    {
        parent::__construct(__('exceptions.idempotency_conflict', ['key' => $key]));
    }
}
