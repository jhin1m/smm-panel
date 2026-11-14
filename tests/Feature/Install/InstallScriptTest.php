<?php

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;

/**
 * Cách cài được tài liệu hoá là `curl ... | sudo bash -s -- ...`: script đọc từ stdin, BASH_SOURCE rỗng.
 * Với `set -u`, đọc thẳng BASH_SOURCE[0] làm script dừng trước khi chạy main.
 */
function runInstallScriptFromStdin(string $args): ProcessResult
{
    return Process::path(base_path())
        ->input(file_get_contents(base_path('deploy/install.sh')))
        ->run('bash -s -- '.$args);
}

it('install.sh chạy được khi đọc từ stdin (curl | bash): --help in tham số, thoát 0', function () {
    $result = runInstallScriptFromStdin('--help');

    expect($result->exitCode())->toBe(0)
        ->and($result->output())->toContain('--domain')
        ->and($result->errorOutput())->not->toContain('unbound variable');
});

it('install.sh đọc từ stdin vẫn vào main: tham số lạ bị từ chối', function () {
    $result = runInstallScriptFromStdin('--bogus');

    expect($result->exitCode())->toBe(1)
        ->and($result->errorOutput())->toContain('--bogus')
        ->and($result->errorOutput())->not->toContain('unbound variable');
});
