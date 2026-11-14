<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            // Snapshot lúc đặt: đổi giá/tên dịch vụ sau không ảnh hưởng đơn cũ.
            $table->string('service_name', 200);
            $table->string('unit', 16);
            $table->bigInteger('unit_price');
            $table->smallInteger('discount_percent')->default(0);
            $table->string('link', 500);
            $table->string('note', 500)->nullable();
            $table->integer('quantity');
            $table->integer('done')->default(0);
            $table->bigInteger('total');
            $table->string('status', 16)->default('pending');
            $table->bigInteger('refunded_amount')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'id']);
            $table->index(['status', 'id']);
        });

        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_quantity_positive CHECK (quantity > 0)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_done_range CHECK (done BETWEEN 0 AND quantity)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_total_non_negative CHECK (total >= 0)');
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_valid CHECK (status IN ('pending', 'processing', 'completed', 'refunded'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
