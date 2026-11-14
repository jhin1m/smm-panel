<?php

namespace App\Exceptions;

use RuntimeException;

/** Yêu cầu cập nhật bị từ chối trước khi ghi request.json; message đã dịch, hiển thị thẳng cho admin. */
class UpdateRejected extends RuntimeException
{
    public static function because(string $reason, array $replace = []): self
    {
        return new self(__("updates.errors.{$reason}", $replace));
    }
}
