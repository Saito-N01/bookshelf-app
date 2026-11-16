<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 公開APIの書籍登録。ルールと文言はWeb版の書籍登録と同じで、次の2点が異なる。
 * - ジャンルは genre_ids で受け取る
 * - 登録者を user_id で受け取る（認証がないため）
 */
class StoreBookRequest extends FormRequest
{
    /**
     * 公開APIのため、誰でも実行できる。
     *
     * @return bool 許可する場合は true（false のときは403になる）
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 書籍登録APIのバリデーションルール。
     *
     * @return array<string, array<int, mixed>> 「項目名 => ルールの配列」の形式
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'isbn' => ['nullable', 'string', 'digits:13', 'unique:books,isbn'],
            'published_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'url', 'max:255'],
            'genre_ids' => ['required', 'array'],
            'genre_ids.*' => ['integer', 'exists:genres,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * バリデーションエラー時の日本語メッセージ。
     *
     * @return array<string, string> 「項目名.ルール名 => メッセージ」の形式
     */
    public function messages(): array
    {
        return [
            'title.required' => 'タイトルは必須です。',
            'title.string' => 'タイトルは文字列で入力してください。',
            'title.max' => 'タイトルは255文字以内で入力してください。',
            'author.required' => '著者名は必須です。',
            'author.string' => '著者名は文字列で入力してください。',
            'author.max' => '著者名は255文字以内で入力してください。',
            'isbn.string' => 'ISBNは文字列で入力してください。',
            'isbn.digits' => 'ISBNは13桁で入力してください。',
            'isbn.unique' => 'そのISBNは既に使用されています。',
            'published_date.date' => '出版日は有効な日付形式で入力してください。',
            'description.string' => '説明は文字列で入力してください。',
            'image_url.url' => '画像URLは有効なURL形式で入力してください。',
            'image_url.max' => '画像URLは255文字以内で入力してください。',
            'genre_ids.required' => 'ジャンルを1つ以上選択してください。',
            'genre_ids.array' => 'ジャンルは配列で入力してください。',
            'genre_ids.*.integer' => '選択されたジャンルが存在しません。',
            'genre_ids.*.exists' => '選択されたジャンルが存在しません。',
            'user_id.required' => '登録者IDは必須です。',
            'user_id.integer' => '登録者IDの値が不正です。',
            'user_id.exists' => '指定された登録者が存在しません。',
        ];
    }

    /**
     * エラーメッセージで使う項目名（日本語）。
     *
     * @return array<string, string> 「項目名 => 日本語の表示名」の形式
     */
    public function attributes(): array
    {
        return [
            'title' => 'タイトル',
            'author' => '著者名',
            'isbn' => 'ISBN',
            'published_date' => '出版日',
            'description' => '説明',
            'image_url' => '画像URL',
            'genre_ids' => 'ジャンル',
            'genre_ids.*' => 'ジャンル',
            'user_id' => '登録者ID',
        ];
    }
}
