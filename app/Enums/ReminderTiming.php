<?php

namespace App\Enums;

/**
 * 読書計画リマインダーの種類（期日に対して、いつ送る通知か）。
 * 値は通知の data.timing に保存され、通知一覧の色とアイコンの切り替えに使われる。
 */
enum ReminderTiming: string
{
    /** 期日の3日前 */
    case ThreeDaysBefore = 'three_days_before';

    /** 期日の当日 */
    case OnDueDate = 'on_due_date';

    /** 期日の3日後（期日を過ぎた計画への再案内） */
    case ThreeDaysAfter = 'three_days_after';

    /**
     * 通知のタイトル。
     *
     * @return string 通知一覧に太字で表示する見出し
     */
    public function title(): string
    {
        return match ($this) {
            self::ThreeDaysBefore => '読書計画の期日が近づいています',
            self::OnDueDate => '今日が読書計画の期日です',
            self::ThreeDaysAfter => '読書計画の期日を過ぎました',
        };
    }

    /**
     * 通知の本文。
     *
     * @param  string  $bookTitle  対象書籍のタイトル
     * @param  string  $targetDate  期日（Y-m-d 形式）
     * @return string 通知一覧に表示する本文
     */
    public function body(string $bookTitle, string $targetDate): string
    {
        return match ($this) {
            self::ThreeDaysBefore => "『{$bookTitle}』の期日（{$targetDate}）まであと3日です。",
            self::OnDueDate => "『{$bookTitle}』の期日は今日（{$targetDate}）です。",
            self::ThreeDaysAfter => "『{$bookTitle}』の期日（{$targetDate}）から3日が過ぎました。期日を変更するか、読了を記録しましょう。",
        };
    }
}
