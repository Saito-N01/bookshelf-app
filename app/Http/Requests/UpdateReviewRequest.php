<?php

namespace App\Http\Requests;

use Illuminate\Validation\Validator;

/**
 * レビューの更新。ルール・メッセージ・項目名は投稿（StoreReviewRequest）と同じで、
 * 認可があることと、投稿済みチェックを行わないことだけが異なる。
 */
class UpdateReviewRequest extends StoreReviewRequest
{
    /**
     * 更新できるのはレビューの投稿者だけ（ReviewPolicy@update）。
     * バリデーションより先に判定されるため、他人のレビューは入力内容にかかわらず403になる。
     *
     * @return bool 許可する場合は true（false のときは403になる）
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('review'));
    }

    /**
     * 更新では投稿済みチェックを行わない（自分のレビューを書き換えるだけのため）。
     *
     * @param  Validator  $validator  追加の検証を登録するバリデーター
     */
    public function withValidator(Validator $validator): void
    {
        //
    }
}
