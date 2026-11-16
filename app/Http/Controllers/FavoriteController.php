<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FavoriteController extends Controller
{
    /**
     * お気に入り一覧。ログインユーザーのお気に入り書籍を10件ずつ表示する。
     *
     * @param  Request  $request  ログインユーザーを取得するためのリクエスト
     * @return View お気に入り一覧画面
     */
    public function index(Request $request): View
    {
        $books = $request->user()
            ->favoriteBooks()
            ->latest('books.created_at')
            ->orderByDesc('books.id')
            ->paginate(10);

        return view('favorites.index', compact('books'));
    }

    /**
     * お気に入りの追加・解除を切り替える。
     * 登録済みなら解除し、未登録なら追加して、元の画面に戻る。
     *
     * @param  Request  $request  ログインユーザーを取得するためのリクエスト
     * @param  Book  $book  お気に入りを切り替える書籍
     * @return RedirectResponse 元の画面へのリダイレクト
     */
    public function toggle(Request $request, Book $book): RedirectResponse
    {
        $request->user()->favoriteBooks()->toggle($book);

        return back();
    }
}
