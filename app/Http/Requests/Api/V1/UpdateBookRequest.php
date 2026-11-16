<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;

/**
 * 公開APIの書籍更新。メッセージ・項目名は登録（StoreBookRequest）と同じで、次の2点が異なる。
 * - ISBNの一意性チェックから自身を除外する
 * - user_id は受け付けない（登録者は変更できない）
 */
class UpdateBookRequest extends StoreBookRequest
{
    /**
     * 書籍更新APIのバリデーションルール。登録のルールを引き継ぎ、ISBN の上書きと user_id の除外を行う。
     *
     * @return array<string, array<int, mixed>> 「項目名 => ルールの配列」の形式
     */
    public function rules(): array
    {
        $rules = parent::rules();

        // 自分自身のISBNは重複とみなさない
        $rules['isbn'] = [
            'required',
            'string',
            'digits:13',
            Rule::unique('books', 'isbn')->ignore($this->route('book')),
        ];

        // ルールから外すことで、送られてきても検証済みデータに含まれず、更新に使われない
        unset($rules['user_id']);

        return $rules;
    }
}
