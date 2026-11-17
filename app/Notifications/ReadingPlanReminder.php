<?php

namespace App\Notifications;

use App\Enums\ReminderTiming;
use App\Models\ReadingPlan;
use Illuminate\Notifications\Notification;

/**
 * 読書計画のリマインダー通知。
 * メールなどは送らず、notifications テーブルに保存して通知一覧に表示する。
 */
class ReadingPlanReminder extends Notification
{
    /**
     * @param  ReadingPlan  $readingPlan  通知の対象になる読書計画
     * @param  ReminderTiming  $timing  通知の種類（3日前 / 当日 / 3日後）
     */
    public function __construct(
        public readonly ReadingPlan $readingPlan,
        public readonly ReminderTiming $timing,
    ) {}

    /**
     * 通知の送り先。database を指定すると、notifications テーブルに保存される（DatabaseChannel）。
     *
     * @param  object  $notifiable  通知を受け取るユーザー
     * @return array<int, string> 使用するチャンネルの一覧
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * notifications.data に保存する内容。
     * timing・title・body は通知一覧の画面が表示に使い、残りの3項目は「どの計画の通知か」の記録として持つ。
     *
     * @param  object  $notifiable  通知を受け取るユーザー
     * @return array{timing: string, title: string, body: string, plan_id: int, book_title: string, target_date: string}
     */
    public function toArray(object $notifiable): array
    {
        $bookTitle = $this->readingPlan->book->title;
        $targetDate = $this->readingPlan->target_date->format('Y-m-d');

        return [
            'timing' => $this->timing->value,
            'title' => $this->timing->title(),
            'body' => $this->timing->body($bookTitle, $targetDate),
            'plan_id' => $this->readingPlan->id,
            'book_title' => $bookTitle,
            'target_date' => $targetDate,
        ];
    }
}
