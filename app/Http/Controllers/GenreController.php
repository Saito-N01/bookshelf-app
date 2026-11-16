<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGenreRequest;
use App\Http\Requests\UpdateGenreRequest;
use App\Models\Genre;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class GenreController extends Controller
{
    /**
     * ジャンル一覧。各ジャンルに紐づく書籍数を表示する。
     *
     * @return View ジャンル一覧画面
     */
    public function index(): View
    {
        $genres = Genre::withCount('books')->orderBy('id')->get();

        return view('genres.index', compact('genres'));
    }

    /**
     * ジャンル登録画面。
     *
     * @return View ジャンル登録画面
     */
    public function create(): View
    {
        return view('genres.create');
    }

    /**
     * ジャンルを登録する。
     *
     * @param  StoreGenreRequest  $request  バリデーション済みの入力値
     * @return RedirectResponse ジャンル一覧へのリダイレクト
     */
    public function store(StoreGenreRequest $request): RedirectResponse
    {
        Genre::create($request->validated());

        return redirect()->route('genres.index')->with('success', 'ジャンルを登録しました。');
    }

    /**
     * ジャンル詳細。紐づく書籍を新しい順に10件ずつ表示する。
     *
     * @param  Genre  $genre  表示するジャンル
     * @return View ジャンル詳細画面
     */
    public function show(Genre $genre): View
    {
        $books = $genre->books()
            ->with('genres')
            ->latest('books.created_at')
            ->orderByDesc('books.id')
            ->paginate(10);

        return view('genres.show', compact('genre', 'books'));
    }

    /**
     * ジャンル編集画面。
     *
     * @param  Genre  $genre  編集するジャンル
     * @return View ジャンル編集画面
     */
    public function edit(Genre $genre): View
    {
        return view('genres.edit', compact('genre'));
    }

    /**
     * ジャンル名を更新する。
     *
     * @param  UpdateGenreRequest  $request  バリデーション済みの入力値
     * @param  Genre  $genre  更新するジャンル
     * @return RedirectResponse ジャンル一覧へのリダイレクト
     */
    public function update(UpdateGenreRequest $request, Genre $genre): RedirectResponse
    {
        $genre->update($request->validated());

        return redirect()->route('genres.index')->with('success', 'ジャンルを更新しました。');
    }

    /**
     * ジャンルを削除する。書籍が紐づいている場合は削除せず、エラーを表示する。
     *
     * @param  Genre  $genre  削除するジャンル
     * @return RedirectResponse ジャンル一覧へのリダイレクト（成功時は success、削除不可のときは error を表示）
     */
    public function destroy(Genre $genre): RedirectResponse
    {
        if ($genre->books()->exists()) {
            return redirect()->route('genres.index')
                ->with('error', '書籍が紐づいているため、このジャンルは削除できません。');
        }

        $genre->delete();

        return redirect()->route('genres.index')->with('success', 'ジャンルを削除しました。');
    }
}
