<?php

/**
 * Xoá trạng thái session/guard giữ trong bộ nhớ giữa 2 request test, để request kế tiếp
 * đọc session thật từ DB theo cookie (mô phỏng trình duyệt quay lại).
 */
function forgetRequestState(): void
{
    app('session')->forgetDrivers();
    app()->forgetInstance('session.store');
    app('auth')->forgetGuards();
}
