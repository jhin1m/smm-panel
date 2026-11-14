<?php

use App\Support\AppVersion;

beforeEach(function () {
    $this->versionFile = tempnam(sys_get_temp_dir(), 'version');
});

afterEach(function () {
    @unlink($this->versionFile);
});

it('đọc phiên bản từ file và bỏ khoảng trắng/xuống dòng', function () {
    file_put_contents($this->versionFile, "1.2.3-rc.1\n");

    expect(AppVersion::fromFile($this->versionFile))->toBe('1.2.3-rc.1');
});

it('file rỗng hoặc chỉ có khoảng trắng → dev', function () {
    file_put_contents($this->versionFile, " \n");

    expect(AppVersion::fromFile($this->versionFile))->toBe('dev');
});

it('không có file VERSION → dev', function () {
    expect(AppVersion::fromFile($this->versionFile.'-missing'))->toBe('dev');
});

it('config app.version luôn là chuỗi không rỗng', function () {
    expect(config('app.version'))->toBeString()->not->toBeEmpty();
});
