<?php

namespace App\Policies;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use App\Models\User;

class ReadingPlanPolicy
{
    /**
     * 読書計画を変更（期日の編集・読了）できるのは、計画者本人で、かつ完了していない計画だけ。
     * 進行中と期限切れの計画は変更でき、完了済みの計画は変更できない。
     *
     * @param  User  $user  操作しようとしているログインユーザー
     * @param  ReadingPlan  $readingPlan  対象の読書計画
     * @return bool 許可する場合は true（false のときは403になる）
     */
    public function update(User $user, ReadingPlan $readingPlan): bool
    {
        return $user->id === $readingPlan->user_id
            && $readingPlan->status !== ReadingPlanStatus::Completed;
    }

    /**
     * 読書計画を削除できるのは計画者本人だけ（状態は問わない）。
     *
     * @param  User  $user  操作しようとしているログインユーザー
     * @param  ReadingPlan  $readingPlan  対象の読書計画
     * @return bool 許可する場合は true（false のときは403になる）
     */
    public function delete(User $user, ReadingPlan $readingPlan): bool
    {
        return $user->id === $readingPlan->user_id;
    }
}
