<?php

namespace App\Http\Controllers;

use App\Http\Requests\FetchBookByIsbnRequest;
use App\Http\Requests\IndexBookRequest;
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Models\Genre;
use App\Services\GoogleBooksService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BookController extends Controller
{
    /**
     * 書籍一覧（トップ）。キーワード・ジャンルで絞り込み、指定の並び順で10件ずつ表示する。
     *
     * @param  IndexBookRequest  $request  検証済みの検索条件（keyword / genre / sort / page）
     * @return View 書籍一覧画面
     */
    public function index(IndexBookRequest $request): View
    {
        $keyword = $request->validated('keyword');
        $genreId = $request->validated('genre');

        $query = Book::with('genres')
            ->withAvg('reviews', 'rating')
            ->when($keyword !== null, function (Builder $query) use ($keyword): void {
                // LIKE の特殊文字（% と _）を、そのままの文字として検索する
                $like = '%'.addcslashes($keyword, '%_\\').'%';

                // orWhere を括弧でまとめ、ジャンルの条件と正しく AND でつながるようにする
                $query->where(function (Builder $query) use ($like): void {
                    $query->where('title', 'like', $like)
                        ->orWhere('author', 'like', $like);
                });
            })
            ->when($genreId !== null, function (Builder $query) use ($genreId): void {
                $query->whereHas('genres', fn (Builder $genres): Builder => $genres->where('genres.id', $genreId));
            });

        // ページを移動しても検索条件が引き継がれるよう、クエリ文字列をリンクに付ける
        $books = $this->applySort($query, $request->validated('sort'))
            ->paginate(10)
            ->withQueryString();

        // 検索フォームのジャンル選択肢
        $genres = Genre::orderBy('id')->get();

        return view('books.index', compact('books', 'genres'));
    }

    /**
     * 並び順を適用する。不正な値や未指定は「新しい順」として扱う。
     * どの並び順でも最後に id で並べ、同じ値の書籍の順番がページごとに変わらないようにする。
     *
     * @param  Builder  $query  並び順を付ける書籍のクエリ
     * @param  mixed  $sort  並び順（oldest / title / rating。それ以外は新しい順）
     * @return Builder 並び順を付けたクエリ
     */
    private function applySort(Builder $query, mixed $sort): Builder
    {
        return match ($sort) {
            'oldest' => $query->oldest()->orderBy('id'),
            'title' => $query->orderBy('title')->orderBy('id'),
            // 平均評価の高い順。レビューのない書籍（平均が NULL）は末尾、同点は新しい順
            'rating' => $query->orderByRaw('reviews_avg_rating IS NULL')
                ->orderByDesc('reviews_avg_rating')
                ->latest()
                ->orderByDesc('id'),
            default => $query->latest()->orderByDesc('id'),
        };
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

    /**
     * ISBN検索。Google Books API から書籍情報を取得し、登録フォームに入力する項目をJSONで返す。
     * 失敗時は {"error": "メッセージ"} を返す（桁数不正は FetchBookByIsbnRequest が422を返す）。
     *
     * @param  FetchBookByIsbnRequest  $request  検証済みのISBN
     * @param  GoogleBooksService  $googleBooks  Google Books API との通信を担当するサービス
     * @return JsonResponse 書籍情報（200）、該当なし（404）、外部APIの障害（503）
     */
    public function fetchByIsbn(FetchBookByIsbnRequest $request, GoogleBooksService $googleBooks): JsonResponse
    {
        try {
            $book = $googleBooks->findByIsbn($request->validated('isbn'));
        } catch (HttpClientException $e) {
            // 原因を調べられるよう、ログには残す
            report($e);

            return response()->json(['error' => '書籍情報を取得できませんでした。時間をおいて再度お試しください。'], 503);
        }

        if ($book === null) {
            return response()->json(['error' => '該当する書籍が見つかりませんでした。'], 404);
        }

        return response()->json($book);
    }
}
