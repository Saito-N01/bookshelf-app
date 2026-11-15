<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    /**
     * レビューを更新できるのは投稿者だけ。
     */
    public function update(User $user, Review $review): bool
    {
        return $user->id === $review->user_id;
    }

    /**
     * レビューを削除できるのは投稿者だけ。
     */
    public function delete(User $user, Review $review): bool
    {
        return $user->id === $review->user_id;
    }

    /**
     * レビューにいいねできるのは、投稿者以外のユーザーだけ（自分のレビューにはいいねできない）。
     */
    public function like(User $user, Review $review): bool
    {
        return $user->id !== $review->user_id;
    }
}
