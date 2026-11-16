<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Models\Genre;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BookController extends Controller
{
    /**
     * 書籍一覧（トップ）。登録日の新しい順に10件ずつ表示する。
     *
     * @return View 書籍一覧画面
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
     * 書籍登録画面。全ジャンルをチェックボックスで表示する。
     *
     * @return View 書籍登録画面
     */
    public function create(): View
    {
        $genres = Genre::all();

        return view('books.create', compact('genres'));
    }

    /**
     * 書籍を登録する。登録者はログインユーザー。
     *
     * @param  StoreBookRequest  $request  バリデーション済みの入力値
     * @return RedirectResponse 登録した書籍の詳細画面へのリダイレクト
     */
    public function store(StoreBookRequest $request): RedirectResponse
    {
        $book = $request->user()->books()->create($request->safe()->except('genres'));
        $book->genres()->sync($request->validated('genres'));

        return redirect()->route('books.show', $book)->with('success', '書籍を登録しました。');
    }

    /**
     * 書籍詳細。ジャンルと、レビュー（投稿者・いいねしたユーザー）を表示する。
     *
     * @param  Book  $book  表示する書籍（URLのIDから自動で取得される）
     * @return View 書籍詳細画面
     */
    public function show(Book $book): View
    {
        $book->load([
            'genres',
            'reviews' => fn (HasMany $query): HasMany => $query->latest()->orderByDesc('id'),
            'reviews.user',
            'reviews.likedByUsers',
        ]);

        return view('books.show', compact('book'));
    }

    /**
     * 書籍編集画面。作成者だけが表示できる。
     *
     * @param  Book  $book  編集する書籍
     * @return View 書籍編集画面
     */
    public function edit(Book $book): View
    {
        $this->authorize('update', $book);

        $book->load('genres');
        $genres = Genre::all();

        return view('books.edit', compact('book', 'genres'));
    }

    /**
     * 書籍を更新する。作成者かどうかは UpdateBookRequest で判定する。
     *
     * @param  UpdateBookRequest  $request  バリデーション済みの入力値
     * @param  Book  $book  更新する書籍
     * @return RedirectResponse 書籍詳細画面へのリダイレクト
     */
    public function update(UpdateBookRequest $request, Book $book): RedirectResponse
    {
        $book->update($request->safe()->except('genres'));
        $book->genres()->sync($request->validated('genres'));

        return redirect()->route('books.show', $book)->with('success', '書籍を更新しました。');
    }

    /**
     * 書籍を削除する。作成者だけが削除できる。
     * レビュー・いいね・お気に入り・ジャンルの紐付けは、外部キーの cascade で削除される。
     *
     * @param  Book  $book  削除する書籍
     * @return RedirectResponse 書籍一覧へのリダイレクト
     */
    public function destroy(Book $book): RedirectResponse
    {
        $this->authorize('delete', $book);

        $book->delete();

        return redirect()->route('books.index')->with('success', '書籍を削除しました。');
    }
}
