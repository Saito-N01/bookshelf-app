<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

/**
 * ジャンルの更新。メッセージ・項目名は登録（StoreGenreRequest）と同じで、
 * ジャンル名の一意性チェックだけが異なる。
 */
class UpdateGenreRequest extends StoreGenreRequest
{
    /**
     * ジャンル更新のバリデーションルール。
     *
     * @return array<string, array<int, mixed>> 「項目名 => ルールの配列」の形式
     */
    public function rules(): array
    {
        return [
            // 自分自身の現在の名前は重複とみなさない
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('genres', 'name')->ignore($this->route('genre')),
            ],
        ];
    }
}
