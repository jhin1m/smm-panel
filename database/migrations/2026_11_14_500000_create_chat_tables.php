<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();   // mỗi khách 1 thread
            $table->unsignedBigInteger('last_message_id')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->unsignedBigInteger('customer_last_read_id')->default(0);
            $table->unsignedBigInteger('admin_last_read_id')->default(0);
            $table->timestamp('admin_notified_at')->nullable();   // đã báo Telegram cho chuỗi tin chưa đọc hiện tại
            $table->timestamps();

            $table->index('last_message_at');
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('chat_threads')->cascadeOnDelete();
            $table->string('sender_type', 8);
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['thread_id', 'id']);
        });

        DB::statement("ALTER TABLE chat_messages ADD CONSTRAINT chat_messages_sender_valid CHECK (sender_type IN ('customer', 'admin'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_threads');
    }
};
