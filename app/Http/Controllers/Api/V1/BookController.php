<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexBookRequest;
use App\Http\Requests\Api\V1\StoreBookRequest;
use App\Http\Requests\Api\V1\UpdateBookRequest;
use App\Http\Resources\BookResource;
use App\Models\Book;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class BookController extends Controller
{
    /**
     * AP01: 書籍一覧。キーワード・ジャンルでの絞り込みと、ページネーションに対応する。
     *
     * @param  IndexBookRequest  $request  検証済みの検索条件（keyword / genre_id / page / per_page）
     * @return AnonymousResourceCollection 書籍の一覧（data / links / meta）
     */
    public function index(IndexBookRequest $request): AnonymousResourceCollection
    {
        $keyword = $request->validated('keyword');
        $genreId = $request->validated('genre_id');
        $perPage = (int) ($request->validated('per_page') ?? 20);

        $books = Book::with('genres')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
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
            })
            ->latest()
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return BookResource::collection($books);
    }

    /**
     * AP03: 書籍登録（要認証）。成功時は 201 Created で、作成した書籍を返す。
     *
     * @param  StoreBookRequest  $request  バリデーション済みの入力値
     * @return JsonResponse 作成した書籍（201 Created）
     */
    public function store(StoreBookRequest $request): JsonResponse
    {
        // 登録者はリクエストから受け取らず、トークンで認証されたユーザーにする
        $book = $request->user()->books()->create($request->safe()->except('genre_ids'));
        $book->genres()->sync($request->validated('genre_ids'));

        return (new BookResource($this->loadForResponse($book)))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * AP02: 書籍詳細。ジャンルと、レビュー（投稿者名つき）を含めて返す。
     *
     * @param  Book  $book  取得する書籍
     * @return BookResource 書籍の詳細
     */
    public function show(Book $book): BookResource
    {
        $book->load([
            'reviews' => fn (HasMany $query): HasMany => $query->latest()->orderByDesc('id'),
            'reviews.user',
        ]);

        return new BookResource($this->loadForResponse($book));
    }

    /**
     * AP04: 書籍更新（要認証）。成功時は 200 OK で、更新後の書籍を返す。
     * 作成者かどうかは UpdateBookRequest で判定する。
     *
     * @param  UpdateBookRequest  $request  バリデーション済みの入力値
     * @param  Book  $book  更新する書籍
     * @return BookResource 更新後の書籍
     */
    public function update(UpdateBookRequest $request, Book $book): BookResource
    {
        $book->update($request->safe()->except('genre_ids'));
        $book->genres()->sync($request->validated('genre_ids'));

        return new BookResource($this->loadForResponse($book));
    }

    /**
     * AP05: 書籍削除（要認証）。作成者だけが削除できる。成功時は 204 No Content（ボディなし）を返す。
     * レビュー・いいね・お気に入り・ジャンルの紐付けは、外部キーの cascade で削除される。
     *
     * @param  Book  $book  削除する書籍
     * @return Response ボディなしのレスポンス（204 No Content）
     */
    public function destroy(Book $book): Response
    {
        $this->authorize('delete', $book);

        $book->delete();

        return response()->noContent();
    }

    /**
     * レスポンスに必要なジャンル・平均評価・レビュー件数を読み込む。
     *
     * @param  Book  $book  読み込み先の書籍
     * @return Book 関連データを読み込んだ書籍
     */
    private function loadForResponse(Book $book): Book
    {
        return $book->load('genres')
            ->loadAvg('reviews', 'rating')
            ->loadCount('reviews');
    }
}
