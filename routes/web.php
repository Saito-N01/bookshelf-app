<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\GenreController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\ReadingPlanController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ReviewLikeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| 会員登録・ログイン・ログアウトのルートは Laravel Fortify が登録する。
|
*/

// ---------- 公開ページ（ゲストも閲覧可） ----------

// 書籍一覧は「/」と「/books」のどちらでも表示する
Route::get('/', [BookController::class, 'index']);
Route::get('/books', [BookController::class, 'index'])->name('books.index');

Route::get('/ranking', [RankingController::class, 'index'])->name('ranking.index');

// ---------- 認証必須（未ログインは /login へ） ----------

Route::middleware('auth')->group(function () {
    // 書籍の登録・編集・削除
    Route::get('/books/create', [BookController::class, 'create'])->name('books.create');
    Route::post('/books', [BookController::class, 'store'])->name('books.store');
    // ISBN検索（登録・編集画面のJavaScriptから呼ばれ、JSONを返す）
    Route::get('/books/isbn/{isbn}', [BookController::class, 'fetchByIsbn'])->name('books.isbn');
    Route::get('/books/{book}/edit', [BookController::class, 'edit'])->name('books.edit');
    Route::put('/books/{book}', [BookController::class, 'update'])->name('books.update');
    Route::delete('/books/{book}', [BookController::class, 'destroy'])->name('books.destroy');

    // レビュー
    Route::post('/books/{book}/reviews', [ReviewController::class, 'store'])->name('reviews.store');
    Route::get('/reviews/{review}/edit', [ReviewController::class, 'edit'])->name('reviews.edit');
    Route::put('/reviews/{review}', [ReviewController::class, 'update'])->name('reviews.update');
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');

    // レビューへのいいね
    Route::post('/reviews/{review}/like', [ReviewLikeController::class, 'toggle'])->name('reviews.like');

    // お気に入り
    Route::post('/books/{book}/favorites', [FavoriteController::class, 'toggle'])->name('favorites.toggle');
    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');

    // ジャンル管理
    Route::resource('genres', GenreController::class);

    // 読書計画（詳細画面はない。URLのパラメータ名は {plan}）
    Route::resource('reading-plans', ReadingPlanController::class)
        ->except('show')
        ->parameters(['reading-plans' => 'plan']);
    Route::post('/reading-plans/{plan}/complete', [ReadingPlanController::class, 'complete'])
        ->name('reading-plans.complete');

    // マイ読書レポート
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');

    // 通知
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
});

// 書籍詳細（公開）。「/books/create」が {book} として扱われないよう、最後に定義する
Route::get('/books/{book}', [BookController::class, 'show'])->name('books.show');
