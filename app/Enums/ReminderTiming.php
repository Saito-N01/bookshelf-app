<?php

namespace App\Enums;

use Illuminate\Support\Carbon;

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

    /**
     * この通知を送る対象になる期日（実行日を基準にして求める）。
     * 3日前の通知 = 期日が3日後の計画、当日の通知 = 期日が今日の計画、3日後の通知 = 期日が3日前の計画。
     *
     * @return Carbon 対象の期日（時刻は 00:00:00）
     */
    public function targetDate(): Carbon
    {
        return match ($this) {
            self::ThreeDaysBefore => today()->addDays(3),
            self::OnDueDate => today(),
            self::ThreeDaysAfter => today()->subDays(3),
        };
    }

    /**
     * この通知を送る対象になる計画の状態。
     * 期日前と当日は進行中の計画、3日後は期限切れの計画（完了済みの計画には送らない）。
     *
     * @return ReadingPlanStatus 対象の状態
     */
    public function targetStatus(): ReadingPlanStatus
    {
        return match ($this) {
            self::ThreeDaysBefore, self::OnDueDate => ReadingPlanStatus::InProgress,
            self::ThreeDaysAfter => ReadingPlanStatus::Expired,
        };
    }

    /**
     * この通知の送信日時を記録する reading_plans のカラム名（二重送信の防止に使う）。
     *
     * @return string カラム名
     */
    public function sentAtColumn(): string
    {
        return match ($this) {
            self::ThreeDaysBefore => 'notified_three_days_before_at',
            self::OnDueDate => 'notified_on_due_at',
            self::ThreeDaysAfter => 'notified_three_days_after_at',
        };
    }
}
