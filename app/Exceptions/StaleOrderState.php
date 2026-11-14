<?php

namespace App\Exceptions;

use RuntimeException;

/** Trạng thái/tiến độ đọc lúc mở modal đã khác bản ghi hiện tại (admin khác vừa cập nhật). */
class StaleOrderState extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('exceptions.stale_order_state'));
    }
}
