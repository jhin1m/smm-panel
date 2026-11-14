<?php

use Illuminate\Support\Facades\Schedule;

// Đối soát SePay 3 ngày gần nhất: bù giao dịch bị lỡ webhook (unique sepay_id chống trùng).
Schedule::command('app:sepay-reconcile')->everyTenMinutes()->withoutOverlapping(15)->onOneServer();

Schedule::command('queue:prune-failed --hours=168')->daily();
Schedule::command('auth:clear-resets')->everyFifteenMinutes();
