<?php

namespace App\Http\Requests;

use App\Models\Review;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreReviewRequest extends FormRequest
{
    /**
     * レビューの投稿はログイン済みなら誰でもできる（ログインの確認は auth ミドルウェアで行う）。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
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
     * @return array<string, string>
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
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $alreadyPosted = Review::where('user_id', $this->user()->id)
                ->where('book_id', $this->route('book')->id)
                ->exists();

            if ($alreadyPosted) {
                $validator->errors()->add('rating', 'この書籍には既にレビューを投稿しています。');
            }
        });
    }
}
