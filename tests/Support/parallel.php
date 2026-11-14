<?php

use Illuminate\Support\Facades\Concurrency;

/**
 * Chạy các closure ở TIẾN TRÌNH PHP RIÊNG (kết nối DB riêng) gần như cùng lúc.
 * Hàng rào thời gian: mọi tiến trình ngủ tới cùng mốc rồi mới chạy, để tranh chấp khoá thật.
 * Exception trong tiến trình con → trả về tên lớp exception (không làm vỡ cả lô).
 *
 * Chỉ dùng trong tests/Concurrency (DatabaseTruncation: dữ liệu seed phải commit để tiến trình con thấy).
 *
 * @param  array<int, Closure>  $tasks
 * @return array<int, mixed>
 */
function runParallel(array $tasks, float $delay = 2.0): array
{
    $startAt = microtime(true) + $delay;
    $wrapped = [];

    foreach ($tasks as $key => $task) {
        $wrapped[$key] = parallelTask($task, $startAt);
    }

    return Concurrency::driver('process')->run($wrapped);
}

/** Bọc 1 task: gỡ $this của lớp test Pest (không tồn tại ở tiến trình con), chờ hàng rào, bắt exception. */
function parallelTask(Closure $task, float $startAt): Closure
{
    $task = Closure::bind($task, null, null);

    return static function () use ($task, $startAt) {
        time_sleep_until($startAt);

        try {
            return $task();
        } catch (Throwable $e) {
            return $e::class;
        }
    };
}
