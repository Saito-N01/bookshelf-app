<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    /**
     * レビューを更新できるのは投稿者だけ。
     *
     * @param  User  $user  操作しようとしているログインユーザー
     * @param  Review  $review  対象のレビュー
     * @return bool 許可する場合は true（false のときは403になる）
     */
    public function update(User $user, Review $review): bool
    {
        return $user->id === $review->user_id;
    }

    /**
     * レビューを削除できるのは投稿者だけ。
     *
     * @param  User  $user  操作しようとしているログインユーザー
     * @param  Review  $review  対象のレビュー
     * @return bool 許可する場合は true（false のときは403になる）
     */
    public function delete(User $user, Review $review): bool
    {
        return $user->id === $review->user_id;
    }

    /**
     * レビューにいいねできるのは、投稿者以外のユーザーだけ（自分のレビューにはいいねできない）。
     *
     * @param  User  $user  操作しようとしているログインユーザー
     * @param  Review  $review  対象のレビュー
     * @return bool 許可する場合は true（false のときは403になる）
     */
    public function like(User $user, Review $review): bool
    {
        return $user->id !== $review->user_id;
    }
}
