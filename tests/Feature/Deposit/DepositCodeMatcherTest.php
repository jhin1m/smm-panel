<?php

use App\Models\User;
use App\Support\DepositCodeMatcher;

beforeEach(function () {
    $this->u = User::factory()->create();
    $this->u->forceFill(['deposit_code' => 'BPABC234'])->save();
    $this->m = new DepositCodeMatcher;
});

it('khớp theo code SePay', function () {
    expect($this->m->match('bpabc234', 'gì cũng được')?->id)->toBe($this->u->id);
});

it('khớp mã trong content có dấu cách/gạch/chữ thường', function () {
    expect($this->m->match(null, 'ck bp-abc234 thanh toan')?->id)->toBe($this->u->id)
        ->and($this->m->match(null, 'B P A B C 2 3 4')?->id)->toBe($this->u->id);
});

it('khớp chuỗi chồng lấn', function () {
    expect($this->m->match(null, 'XBPBPABC234Y')?->id)->toBe($this->u->id);
});

it('mã không tồn tại → null', function () {
    expect($this->m->match('BPZZZZZZ', 'BPZZZZZZ'))->toBeNull()
        ->and($this->m->match(null, ''))->toBeNull();
});
