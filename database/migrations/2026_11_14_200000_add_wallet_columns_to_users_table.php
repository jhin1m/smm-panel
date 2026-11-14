<?php

use App\Support\DepositCode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->bigInteger('balance')->default(0);
            $table->bigInteger('total_deposited')->default(0);
            $table->string('tier', 16)->default('member');
            $table->timestamp('tier_changed_at')->nullable();
            $table->boolean('tier_manual')->default(false);    // admin đổi cấp tay → không tự nâng nữa
            $table->string('deposit_code', 8)->nullable()->unique();
        });

        // Backfill mã nạp cho user có sẵn rồi mới ép NOT NULL.
        DB::table('users')->whereNull('deposit_code')->orderBy('id')->each(function ($u) {
            DB::table('users')->where('id', $u->id)->update(['deposit_code' => DepositCode::unique()]);
        });

        DB::statement('ALTER TABLE users ALTER COLUMN deposit_code SET NOT NULL');
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_balance_non_negative CHECK (balance >= 0)');
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_total_deposited_non_negative CHECK (total_deposited >= 0)');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_tier_valid CHECK (tier IN ('member', 'agency'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_balance_non_negative');
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_total_deposited_non_negative');
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_tier_valid');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['balance', 'total_deposited', 'tier', 'tier_changed_at', 'tier_manual', 'deposit_code']);
        });
    }
};
