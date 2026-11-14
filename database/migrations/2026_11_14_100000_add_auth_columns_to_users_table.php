<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();          // user Google chưa đặt mật khẩu
            $table->string('google_id')->nullable()->unique();
            $table->boolean('is_admin')->default(false);
            $table->timestamp('locked_at')->nullable();
            $table->text('app_authentication_secret')->nullable();       // MFA TOTP Filament (mã hoá)
            $table->text('app_authentication_recovery_codes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['google_id', 'is_admin', 'locked_at', 'app_authentication_secret', 'app_authentication_recovery_codes']);
        });
    }
};
