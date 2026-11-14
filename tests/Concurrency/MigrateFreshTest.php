<?php

use Illuminate\Support\Facades\DB;

// Ở suite Concurrency (không bọc transaction) để chạy migrate:fresh thật.
it('migrate:fresh chạy 2 lần liên tiếp không lỗi, trigger sổ cái còn đúng 1', function () {
    $this->artisan('migrate:fresh')->assertSuccessful();
    $this->artisan('migrate:fresh')->assertSuccessful();

    expect(DB::selectOne("select count(*) as c from pg_trigger where tgname = 'wallet_tx_no_update_delete'")->c)->toBe(1)
        ->and(DB::selectOne("select count(*) as c from pg_proc where proname = 'wallet_tx_append_only'")->c)->toBe(1);
});
