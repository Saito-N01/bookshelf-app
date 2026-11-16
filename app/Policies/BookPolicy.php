<?php

namespace App\Policies;

use App\Models\Book;
use App\Models\User;

class BookPolicy
{
    /**
     * 書籍を更新できるのは作成者だけ。
     *
     * @param  User  $user  操作しようとしているログインユーザー
     * @param  Book  $book  対象の書籍
     * @return bool 許可する場合は true（false のときは403になる）
     */
    public function update(User $user, Book $book): bool
    {
        return $user->id === $book->user_id;
    }

    /**
     * 書籍を削除できるのは作成者だけ。
     *
     * @param  User  $user  操作しようとしているログインユーザー
     * @param  Book  $book  対象の書籍
     * @return bool 許可する場合は true（false のときは403になる）
     */
    public function delete(User $user, Book $book): bool
    {
        return $user->id === $book->user_id;
    }
}
