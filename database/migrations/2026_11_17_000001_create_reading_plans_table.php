<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 読書計画（「どの書籍を、いつまでに読むか」）のテーブルを作成する。
     */
    public function up(): void
    {
        Schema::create('reading_plans', function (Blueprint $table) {
            $table->id();
            // ユーザーや書籍が削除されたら、その読書計画も一緒に削除する
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->date('target_date');
            // App\Enums\ReadingPlanStatus の値（in_progress / completed / expired）
            $table->string('status', 20)->default('in_progress');
            $table->timestamp('completed_at')->nullable();
            // リマインダーの送信日時。NULL = 未送信。同じ通知を二重に送らないための判定に使う
            $table->timestamp('notified_three_days_before_at')->nullable();
            $table->timestamp('notified_on_due_at')->nullable();
            $table->timestamp('notified_three_days_after_at')->nullable();
            $table->timestamps();

            // 一覧の状態絞り込み・進行中の重複チェック用
            $table->index(['user_id', 'status']);
            // 日次バッチの対象抽出用
            $table->index(['status', 'target_date']);
        });
    }

    /**
     * テーブルを削除する。
     */
    public function down(): void
    {
        Schema::dropIfExists('reading_plans');
    }
};
