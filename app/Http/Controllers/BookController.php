<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\View\View;

class BookController extends Controller
{
    /**
     * 書籍一覧（トップ）。登録日の新しい順に10件ずつ表示する。
     */
    public function index(): View
    {
        $books = Book::with('genres')
            ->withAvg('reviews', 'rating')
            ->latest()
            ->orderByDesc('id')
            ->paginate(10);

        return view('books.index', compact('books'));
    }

    /**
     * 書籍詳細。ジャンルと、レビュー（投稿者・いいねしたユーザー）を表示する。
     */
    public function show(Book $book): View
    {
        $book->load([
            'genres',
            'reviews' => fn ($query) => $query->latest()->orderByDesc('id'),
            'reviews.user',
            'reviews.likedByUsers',
        ]);

        return view('books.show', compact('book'));
    }
}
