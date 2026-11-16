<?php

use App\Http\Controllers\Api\V1\AuthTokenController;
use App\Http\Controllers\Api\V1\BookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| 公開API。URLには RouteServiceProvider で「/api」が付く。
| 読み取り（GET）は認証なし、書き込み（POST / PUT / DELETE）は Sanctum のトークン認証が必要。
| ルート名には「api.v1.」を付け、Web側の books.index などと重ならないようにする。
|
*/

Route::prefix('v1')->name('api.v1.')->group(function () {
    // ログイン（トークン発行）
    Route::post('login', [AuthTokenController::class, 'store'])->name('login');

    // 読み取り（認証なし）: GET /books, GET /books/{book}
    Route::apiResource('books', BookController::class)->only(['index', 'show']);

    // 書き込み（Authorization: Bearer {token} が必要）: POST /books, PUT /books/{book}, DELETE /books/{book}
    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('books', BookController::class)->only(['store', 'update', 'destroy']);
    });
});
