<?php

namespace App\Console\Commands;

use App\Actions\CreateOrResetAdmin;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Tạo admin hoặc ghi đè user có sẵn thành admin. Luôn đặt mật khẩu MỚI + xoá MFA cũ + huỷ mọi phiên:
 * không cấp quyền admin cho TK mà người khác có thể đang giữ mật khẩu (chiếm trước email).
 */
#[Signature('app:make-admin')]
#[Description('Tạo/ghi đè tài khoản quản trị (đặt mật khẩu mới, bắt thiết lập MFA lần đăng nhập đầu)')]
class MakeAdmin extends Command
{
    public function handle(CreateOrResetAdmin $createAdmin): int
    {
        $email = User::normalizeEmail($this->ask('Email'));
        $existing = User::where('email', $email)->first();
        $name = trim((string) $this->ask('Tên hiển thị', $existing?->name ?? 'Quản trị viên'));
        $password = (string) $this->secret('Mật khẩu (tối thiểu 12 ký tự)');
        $confirm = (string) $this->secret('Nhập lại mật khẩu');

        $v = Validator::make(
            ['email' => $email, 'name' => $name, 'password' => $password, 'password_confirmation' => $confirm],
            ['email' => ['required', 'email', 'max:255'], 'name' => ['required', 'max:50'], 'password' => ['required', 'min:12', 'confirmed']],
        );

        if ($v->fails()) {
            foreach ($v->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        if ($existing && ! $this->confirm("User {$email} đã tồn tại. Ghi đè mật khẩu, xoá MFA và cấp quyền admin?", true)) {
            return self::FAILURE;
        }

        $createAdmin->handle($email, $name, $password);

        $this->info("Đã {$this->verb($existing)} admin {$email}. Đăng nhập /admin để thiết lập MFA ngay.");

        return self::SUCCESS;
    }

    private function verb(?User $existing): string
    {
        return $existing ? 'ghi đè' : 'tạo';
    }
}
