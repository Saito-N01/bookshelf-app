<?php

namespace Database\Seeders;

use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewLikeSeeder extends Seeder
{
    /**
     * 各レビューに0〜3人のユーザーがいいねする（自分のレビューは除く）。
     * レビューの並び順に応じて 0→1→2→3人 を繰り返す固定の割り当てにしている。
     */
    public function run(): void
    {
        $users = User::orderBy('id')->get();

        Review::orderBy('id')->get()->each(function (Review $review, int $index) use ($users) {
            // 投稿者本人を除いたユーザーを、レビューごとに開始位置をずらして使う
            $others = $users->where('id', '!=', $review->user_id)->values();
            $likeCount = $index % 4;

            $likerIds = $others->concat($others)
                ->slice($index % $others->count(), $likeCount)
                ->pluck('id')
                ->all();

            $review->likedByUsers()->syncWithoutDetaching($likerIds);
        });
    }
}
