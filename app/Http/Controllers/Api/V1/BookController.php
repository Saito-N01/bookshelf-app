<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexBookRequest;
use App\Http\Requests\Api\V1\StoreBookRequest;
use App\Http\Requests\Api\V1\UpdateBookRequest;
use App\Http\Resources\BookResource;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class BookController extends Controller
{
    /**
     * AP01: 書籍一覧。キーワード・ジャンルでの絞り込みと、ページネーションに対応する。
     */
    public function index(IndexBookRequest $request): AnonymousResourceCollection
    {
        $keyword = $request->validated('keyword');
        $genreId = $request->validated('genre_id');
        $perPage = (int) ($request->validated('per_page') ?? 20);

        $books = Book::with('genres')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->when($keyword !== null, function ($query) use ($keyword) {
                // LIKE の特殊文字（% と _）を、そのままの文字として検索する
                $like = '%'.addcslashes($keyword, '%_\\').'%';

                // orWhere を括弧でまとめ、ジャンルの条件と正しく AND でつながるようにする
                $query->where(function ($query) use ($like) {
                    $query->where('title', 'like', $like)
                        ->orWhere('author', 'like', $like);
                });
            })
            ->when($genreId !== null, function ($query) use ($genreId) {
                $query->whereHas('genres', fn ($genres) => $genres->where('genres.id', $genreId));
            })
            ->latest()
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return BookResource::collection($books);
    }

    /**
     * AP03: 書籍登録。成功時は 201 Created で、作成した書籍を返す。
     */
    public function store(StoreBookRequest $request): JsonResponse
    {
        $book = Book::create($request->safe()->except('genre_ids'));
        $book->genres()->sync($request->validated('genre_ids'));

        return (new BookResource($this->loadForResponse($book)))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * AP02: 書籍詳細。ジャンルと、レビュー（投稿者名つき）を含めて返す。
     */
    public function show(Book $book): BookResource
    {
        $book->load([
            'reviews' => fn ($query) => $query->latest()->orderByDesc('id'),
            'reviews.user',
        ]);

        return new BookResource($this->loadForResponse($book));
    }

    /**
     * AP04: 書籍更新。成功時は 200 OK で、更新後の書籍を返す。
     */
    public function update(UpdateBookRequest $request, Book $book): BookResource
    {
        $book->update($request->safe()->except('genre_ids'));
        $book->genres()->sync($request->validated('genre_ids'));

        return new BookResource($this->loadForResponse($book));
    }

    /**
     * AP05: 書籍削除。成功時は 204 No Content（ボディなし）を返す。
     * レビュー・いいね・お気に入り・ジャンルの紐付けは、外部キーの cascade で削除される。
     */
    public function destroy(Book $book): Response
    {
        $book->delete();

        return response()->noContent();
    }

    /**
     * レスポンスに必要なジャンル・平均評価・レビュー件数を読み込む。
     */
    private function loadForResponse(Book $book): Book
    {
        return $book->load('genres')
            ->loadAvg('reviews', 'rating')
            ->loadCount('reviews');
    }
}
