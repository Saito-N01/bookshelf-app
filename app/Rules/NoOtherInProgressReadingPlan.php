<?php

namespace App\Rules;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;

/**
 * 同じユーザーが、同じ書籍について「進行中」の読書計画を2件以上持てないようにするルール。
 * 完了・期限切れの計画があっても、新しい計画は登録できる。
 */
class NoOtherInProgressReadingPlan implements ValidationRule
{
    /**
     * @param  int  $userId  計画者のユーザーID
     * @param  int|null  $bookId  対象の書籍ID。null のときは、検証中の値（book_id）を書籍IDとして使う
     * @param  int|null  $ignorePlanId  重複チェックから除外する計画ID（編集時は、編集中の計画自身を除外する）
     */
    public function __construct(
        private readonly int $userId,
        private readonly ?int $bookId = null,
        private readonly ?int $ignorePlanId = null,
    ) {}

    /**
     * 進行中の計画がほかにある場合は、エラーにする。
     *
     * @param  string  $attribute  検証中の項目名
     * @param  mixed  $value  検証中の値
     * @param  Closure  $fail  エラーメッセージを登録するためのコールバック
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $exists = ReadingPlan::where('user_id', $this->userId)
            ->where('book_id', $this->bookId ?? $value)
            ->where('status', ReadingPlanStatus::InProgress)
            ->when(
                $this->ignorePlanId !== null,
                fn (Builder $query): Builder => $query->whereKeyNot($this->ignorePlanId)
            )
            ->exists();

        if ($exists) {
            $fail('この書籍の読書計画は既に進行中です。');
        }
    }
}
