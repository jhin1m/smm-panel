<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->string('unit', 16)->default('per_1000');
            $table->bigInteger('price');                 // VND theo đơn vị tính
            $table->integer('min_quantity');
            $table->integer('max_quantity');
            $table->string('speed', 50)->nullable();     // vd "10.000/ngày"
            $table->string('start_time', 50)->nullable(); // vd "5 phút"
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['category_id', 'is_active', 'sort_order']);
        });

        DB::statement("ALTER TABLE services ADD CONSTRAINT services_unit_valid CHECK (unit IN ('per_1000', 'per_unit'))");
        DB::statement('ALTER TABLE services ADD CONSTRAINT services_price_non_negative CHECK (price >= 0)');
        DB::statement('ALTER TABLE services ADD CONSTRAINT services_min_positive CHECK (min_quantity >= 1)');
        DB::statement('ALTER TABLE services ADD CONSTRAINT services_max_gte_min CHECK (max_quantity >= min_quantity)');
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
