<?php

use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\ConcurrencyTestCase;
use Tests\TestCase;

/*
| Mặc định instance đã cài (cờ installed_at); test của wizard gọi markNotInstalled().
| Feature: mỗi test trong transaction (RefreshDatabase).
| Concurrency: tiến trình con cần thấy dữ liệu đã commit → truncation thay vì transaction.
*/
pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature')->beforeEach(fn () => markInstalled());
pest()->extend(ConcurrencyTestCase::class)->use(DatabaseTruncation::class)->in('Concurrency')->beforeEach(fn () => markInstalled());
pest()->extend(TestCase::class)->in('Unit');

foreach (glob(__DIR__.'/Support/*.php') as $helper) {
    require_once $helper;
}
