<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Laravel 標準の通知テーブルを作成する。
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // 通知クラス名
            $table->string('type');
            // 通知の受信者（notifiable_type = App\Models\User、notifiable_id = ユーザーID）
            $table->morphs('notifiable');
            // 通知の内容（JSON文字列）
            $table->text('data');
            // NULL = 未読、日時あり = 既読
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * テーブルを削除する。
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
