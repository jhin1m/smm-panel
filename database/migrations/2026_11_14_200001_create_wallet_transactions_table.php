<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sổ cái số dư append-only. Mọi thay đổi users.balance đi kèm đúng 1 dòng ở đây (qua WalletService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('type', 16);
            $table->bigInteger('amount');
            $table->bigInteger('balance_after');
            $table->string('idempotency_key', 100)->unique();
            $table->nullableMorphs('reference');
            $table->string('description', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'id']);
            $table->index(['type', 'created_at']);
        });

        DB::statement("ALTER TABLE wallet_transactions ADD CONSTRAINT wallet_tx_type_valid CHECK (type IN ('topup','bonus','order','refund','adjust','topup_reversal','bonus_reversal'))");
        DB::statement('ALTER TABLE wallet_transactions ADD CONSTRAINT wallet_tx_amount_non_zero CHECK (amount <> 0)');
        DB::statement('ALTER TABLE wallet_transactions ADD CONSTRAINT wallet_tx_balance_after_non_negative CHECK (balance_after >= 0)');

        // Chặn UPDATE/DELETE ở tầng DB. Viết idempotent: function sống sót khi drop bảng (migrate:fresh).
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION wallet_tx_append_only() RETURNS trigger LANGUAGE plpgsql AS
            $$ BEGIN RAISE EXCEPTION 'wallet_transactions is append-only'; END $$;
            DROP TRIGGER IF EXISTS wallet_tx_no_update_delete ON wallet_transactions;
            CREATE TRIGGER wallet_tx_no_update_delete BEFORE UPDATE OR DELETE ON wallet_transactions
            FOR EACH ROW EXECUTE FUNCTION wallet_tx_append_only();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS wallet_tx_no_update_delete ON wallet_transactions; DROP FUNCTION IF EXISTS wallet_tx_append_only();');
        Schema::dropIfExists('wallet_transactions');
    }
};
