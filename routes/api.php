<?php

use App\Http\Controllers\Api\V1\BookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| 公開API（認証なし）。URLには RouteServiceProvider で「/api」が付く。
| ルート名には「api.v1.」を付け、Web側の books.index などと重ならないようにする。
|
*/

Route::prefix('v1')->name('api.v1.')->group(function () {
    // GET /books, POST /books, GET /books/{book}, PUT /books/{book}, DELETE /books/{book}
    Route::apiResource('books', BookController::class);
});
