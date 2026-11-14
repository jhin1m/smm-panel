<?php

namespace Tests;

/**
 * Suite song song: truncation thay vì transaction. Giữ bảng settings (dữ liệu từ settings migration).
 */
abstract class ConcurrencyTestCase extends TestCase
{
    /** @var array<int, string> */
    protected $exceptTables = ['settings'];
}
