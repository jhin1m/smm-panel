<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Giao dịch ngân hàng nhận từ SePay (webhook / đối soát / nhập tay). Không bao giờ xoá. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('sepay_id')->unique();          // id giao dịch SePay — khoá chống trùng
            $table->string('source', 10);
            $table->string('gateway', 50)->nullable();
            $table->string('account_number', 50)->nullable();
            $table->string('sub_account', 50)->nullable();
            $table->string('transfer_type', 3);
            $table->bigInteger('transfer_amount');
            $table->string('code', 50)->nullable();
            $table->text('content');
            $table->string('reference_code', 100)->nullable();
            $table->timestamp('transaction_date')->nullable();  // giờ VN
            $table->jsonb('payload');
            $table->smallInteger('bonus_percent')->default(0);  // chốt lúc nhận, gán tay sau vẫn dùng mức này
            $table->bigInteger('bonus_amount')->default(0);
            $table->integer('assignment_no')->default(0);       // lần gán thứ n → key sổ cái mới mỗi lần gán lại
            $table->string('status', 16);
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('note', 255)->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('credited_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'id']);
            $table->index('transaction_date');
            $table->index('reference_code');
        });

        DB::statement("ALTER TABLE bank_transactions ADD CONSTRAINT bank_tx_source_valid CHECK (source IN ('webhook', 'reconcile', 'manual'))");
        DB::statement("ALTER TABLE bank_transactions ADD CONSTRAINT bank_tx_status_valid CHECK (status IN ('pending', 'credited', 'unmatched', 'ignored'))");
        DB::statement("ALTER TABLE bank_transactions ADD CONSTRAINT bank_tx_type_valid CHECK (transfer_type IN ('in', 'out'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
    }
};
