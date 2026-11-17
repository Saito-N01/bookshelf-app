<?php

namespace App\Http\Requests;

use App\Rules\NoOtherInProgressReadingPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReadingPlanRequest extends FormRequest
{
    /**
     * 読書計画の登録はログイン済みなら誰でもできる（ログインの確認は auth ミドルウェアで行う）。
     *
     * @return bool 許可する場合は true（false のときは403になる）
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 読書計画登録のバリデーションルール。
     *
     * bail は、最初に失敗したルールで検証を止める指定。
     * 書籍IDが不正なときに、重複チェックのためのDB検索まで実行しないようにしている。
     *
     * @return array<string, array<int, mixed>> 「項目名 => ルールの配列」の形式
     */
    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'book_id' => [
                'bail',
                'required',
                'integer',
                // 選べるのは、自分が登録した書籍だけ（作成画面のプルダウンと同じ範囲）
                Rule::exists('books', 'id')->where('user_id', $userId),
                new NoOtherInProgressReadingPlan($userId),
            ],
            'target_date' => ['required', 'date', 'after_or_equal:today'],
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
            'book_id.required' => '書籍は必須です。',
            'book_id.integer' => '選択された書籍が存在しません。',
            'book_id.exists' => '選択された書籍が存在しません。',
            'target_date.required' => '期日は必須です。',
            'target_date.date' => '期日は有効な日付形式で入力してください。',
            'target_date.after_or_equal' => '期日は今日以降の日付を指定してください。',
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
            'book_id' => '書籍',
            'target_date' => '期日',
        ];
    }
}
