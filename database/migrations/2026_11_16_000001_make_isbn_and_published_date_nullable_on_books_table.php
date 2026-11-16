<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ISBN と出版日を任意項目（NULL可）に変更する。
     * ISBN検索で出版日が取得できない書籍や、ISBNのない書籍も登録できるようにするため。
     * isbn の UNIQUE 制約はそのまま残る（NULL は重複とみなされない）。
     */
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('isbn', 13)->nullable()->change();
            $table->date('published_date')->nullable()->change();
        });
    }

    /**
     * 必須項目（NOT NULL）に戻す。NULL のデータが残っていると失敗する。
     */
    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('isbn', 13)->nullable(false)->change();
            $table->date('published_date')->nullable(false)->change();
        });
    }
};
