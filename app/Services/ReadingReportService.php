<?php

namespace App\Services;

use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * マイ読書レポートの集計。ログインユーザー本人のレビューだけを対象にする。
 * 集計は、DBから取得したレビューの Collection に対して、Collection メソッドで行う（foreach は使わない）。
 */
class ReadingReportService
{
    /**
     * レポート画面に渡す統計情報をまとめて作る。
     *
     * @param  User  $user  集計するユーザー
     * @return array{
     *     summary: array{total_reviews: int, books_read: int, average_rating: float|null},
     *     rating_distribution: Collection<int, int>,
     *     top_rated_books: Collection<int, array{id: int, title: string, author: string, rating: int}>,
     *     genre_ratings: Collection<int, array{id: int, name: string, average_rating: float, count: int}>
     * }
     */
    public function statsFor(User $user): array
    {
        // 書籍とジャンルも一緒に読み込み、集計中にSQLが繰り返し発行されないようにする（N+1対策）
        $reviews = $user->reviews()->with('book.genres')->get();

        return [
            'summary' => $this->summary($reviews),
            'rating_distribution' => $this->ratingDistribution($reviews),
            'top_rated_books' => $this->topRatedBooks($reviews),
            'genre_ratings' => $this->genreRatings($reviews),
        ];
    }

    /**
     * 基本サマリー（総レビュー数・読了冊数・平均評価）。
     * 読了冊数は「レビューを書いた書籍の数（重複なし）」で、読書計画の完了は含めない。
     * 平均評価は小数第1位に丸め、レビューが0件のときは null（画面では「-」と表示）。
     *
     * @param  Collection<int, Review>  $reviews  集計対象のレビュー
     * @return array{total_reviews: int, books_read: int, average_rating: float|null}
     */
    private function summary(Collection $reviews): array
    {
        return [
            'total_reviews' => $reviews->count(),
            'books_read' => $reviews->unique('book_id')->count(),
            'average_rating' => $reviews->isEmpty() ? null : round($reviews->avg('rating'), 1),
        ];
    }

    /**
     * 評価分布。1星〜5星それぞれのレビュー件数を、1星から順に並べる。
     * 画面は「添字 + 1」を星の数として表示するため、キーは 0〜4 になる。
     *
     * @param  Collection<int, Review>  $reviews  集計対象のレビュー
     * @return Collection<int, int> [1星の件数, 2星の件数, ..., 5星の件数]
     */
    private function ratingDistribution(Collection $reviews): Collection
    {
        // countBy は「評価 => 件数」を返す。レビューがない評価はキー自体がないため、0 で補う
        $countsByRating = $reviews->countBy('rating');

        return collect(range(1, 5))->map(fn (int $rating): int => $countsByRating->get($rating, 0));
    }

    /**
     * 高評価書籍TOP5。評価4以上のレビューを、評価の高い順に最大5件。
     * 同じ評価のときは、レビューを新しく書いた順にする。
     *
     * @param  Collection<int, Review>  $reviews  集計対象のレビュー
     * @return Collection<int, array{id: int, title: string, author: string, rating: int}>
     */
    private function topRatedBooks(Collection $reviews): Collection
    {
        return $reviews
            ->filter(fn (Review $review): bool => $review->rating >= 4)
            ->sortBy([
                ['rating', 'desc'],
                ['created_at', 'desc'],
                ['id', 'desc'],
            ])
            ->take(5)
            ->map(fn (Review $review): array => [
                'id' => $review->book->id,
                'title' => $review->book->title,
                'author' => $review->book->author,
                'rating' => $review->rating,
            ])
            // 画面は添字（0, 1, 2...）で順位を表示するため、キーを振り直す
            ->values();
    }

    /**
     * ジャンル別評価傾向TOP5。ジャンルごとに、そのジャンルの書籍へのレビューの平均評価と件数を求め、
     * 平均評価の高い順に最大5件。同じ平均評価のときは件数の多い順、さらに同じならジャンルIDの小さい順。
     * 1冊が複数のジャンルを持つ場合、そのレビューは各ジャンルに1件ずつ数える。
     *
     * @param  Collection<int, Review>  $reviews  集計対象のレビュー
     * @return Collection<int, array{id: int, name: string, average_rating: float, count: int}>
     */
    private function genreRatings(Collection $reviews): Collection
    {
        return $reviews
            // 「レビュー × そのレビューの書籍のジャンル」の組み合わせを1つの一覧に展開する
            ->flatMap(fn (Review $review): Collection => $review->book->genres->map(
                fn (Genre $genre): array => ['genre' => $genre, 'rating' => $review->rating]
            ))
            ->groupBy(fn (array $item): int => $item['genre']->id)
            ->map(fn (Collection $items): array => [
                'id' => $items->first()['genre']->id,
                'name' => $items->first()['genre']->name,
                'average_rating' => round($items->avg('rating'), 1),
                'count' => $items->count(),
            ])
            ->sortBy([
                ['average_rating', 'desc'],
                ['count', 'desc'],
                ['id', 'asc'],
            ])
            ->take(5)
            ->values();
    }
}
