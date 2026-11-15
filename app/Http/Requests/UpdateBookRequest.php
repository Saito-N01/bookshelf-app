<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

/**
 * 書籍の更新。ルール・メッセージ・項目名は登録（StoreBookRequest）と同じで、
 * 認可と ISBN の一意性チェックだけが異なる。
 */
class UpdateBookRequest extends StoreBookRequest
{
    /**
     * 更新できるのは書籍の作成者だけ（BookPolicy@update）。
     * バリデーションより先に判定されるため、他人の書籍は入力内容にかかわらず403になる。
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('book'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            // 自分自身のISBNは重複とみなさない
            'isbn' => [
                'required',
                'string',
                'digits:13',
                Rule::unique('books', 'isbn')->ignore($this->route('book')),
            ],
        ]);
    }
}
