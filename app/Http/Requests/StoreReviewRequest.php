<?php

namespace App\Http\Requests;

use App\Models\Review;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreReviewRequest extends FormRequest
{
    /**
     * レビューの投稿はログイン済みなら誰でもできる（ログインの確認は auth ミドルウェアで行う）。
     *
     * @return bool 許可する場合は true（false のときは403になる）
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * レビュー投稿のバリデーションルール。
     *
     * @return array<string, array<int, mixed>> 「項目名 => ルールの配列」の形式
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
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
            'rating.required' => '評価は必須です。',
            'rating.integer' => '評価は1〜5の整数で選択してください。',
            'rating.between' => '評価は1〜5の整数で選択してください。',
            'comment.string' => 'コメントは文字列で入力してください。',
            'comment.max' => 'コメントは1000文字以内で入力してください。',
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
            'rating' => '評価',
            'comment' => 'コメント',
        ];
    }

    /**
     * 投稿済みチェック。同じユーザーは、同じ書籍にレビューを1件しか投稿できない。
     *
     * @param  Validator  $validator  追加の検証を登録するバリデーター
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $alreadyPosted = Review::where('user_id', $this->user()->id)
                ->where('book_id', $this->route('book')->id)
                ->exists();

            if ($alreadyPosted) {
                $validator->errors()->add('rating', 'この書籍には既にレビューを投稿しています。');
            }
        });
    }
}
