<?php

namespace App\Http\Requests;

use App\Rules\NoOtherInProgressReadingPlan;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 読書計画の更新。変更できるのは期日だけ（書籍と状態は画面から変更できない）。
 */
class UpdateReadingPlanRequest extends FormRequest
{
    /**
     * 更新できるのは、計画者本人の、完了していない計画だけ（ReadingPlanPolicy@update）。
     * バリデーションより先に判定されるため、他人の計画や完了済みの計画は入力内容にかかわらず403になる。
     *
     * @return bool 許可する場合は true（false のときは403になる）
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('plan'));
    }

    /**
     * 読書計画更新のバリデーションルール。
     *
     * 期限切れの計画は、期日を変更すると進行中に戻る。そのとき同じ書籍の進行中の計画が
     * 2件にならないよう、自分自身を除いて重複をチェックする。
     *
     * @return array<string, array<int, mixed>> 「項目名 => ルールの配列」の形式
     */
    public function rules(): array
    {
        $plan = $this->route('plan');

        return [
            'target_date' => [
                'bail',
                'required',
                'date',
                'after_or_equal:today',
                new NoOtherInProgressReadingPlan($this->user()->id, $plan->book_id, $plan->id),
            ],
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
            'target_date' => '期日',
        ];
    }
}
