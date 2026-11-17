<?php

namespace App\Console\Commands;

use App\Enums\ReadingPlanStatus;
use App\Enums\ReminderTiming;
use App\Models\ReadingPlan;
use App\Notifications\ReadingPlanReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 読書計画の日次バッチ。毎日 20:00 にスケジュールから実行される（手動でも実行できる）。
 */
class SendReadingPlanReminders extends Command
{
    /**
     * コマンド名（sail artisan reading-plans:notify）。
     *
     * @var string
     */
    protected $signature = 'reading-plans:notify';

    /**
     * コマンドの説明（sail artisan list に表示される）。
     *
     * @var string
     */
    protected $description = '期日を過ぎた読書計画を期限切れにし、リマインダー通知（3日前・当日・3日後）を送信する';

    /**
     * バッチの本体。次の順に実行する。
     *  1. 期日を過ぎた進行中の計画を、期限切れに更新する
     *  2. リマインダー通知を送信する（期日の3日前 / 当日 / 3日後）
     *
     * 先に期限切れへ更新するのは、「3日後」の通知が期限切れの計画を対象にしているため。
     *
     * @return int 終了コード（成功は 0）
     */
    public function handle(): int
    {
        $expiredCount = $this->expireOverduePlans();
        $this->info("期限切れに更新: {$expiredCount}件");

        foreach (ReminderTiming::cases() as $timing) {
            $sentCount = $this->sendReminders($timing);
            $this->info("通知を送信（{$timing->title()}）: {$sentCount}件");
        }

        return self::SUCCESS;
    }

    /**
     * 期日を過ぎた進行中の計画を、期限切れに更新する。
     * 期日が今日の計画は、まだ期限内として扱う。
     *
     * @return int 更新した件数
     */
    private function expireOverduePlans(): int
    {
        return ReadingPlan::where('status', ReadingPlanStatus::InProgress)
            ->whereDate('target_date', '<', today())
            ->update(['status' => ReadingPlanStatus::Expired]);
    }

    /**
     * 指定した種類のリマインダーを、対象の計画すべてに送信する。
     * 送信済みの計画（送信日時が入っている計画）は対象から外すため、再実行しても二重に送らない。
     *
     * @param  ReminderTiming  $timing  通知の種類（3日前 / 当日 / 3日後）
     * @return int 送信した件数
     */
    private function sendReminders(ReminderTiming $timing): int
    {
        $column = $timing->sentAtColumn();

        $plans = ReadingPlan::with(['user', 'book'])
            ->where('status', $timing->targetStatus())
            ->whereDate('target_date', $timing->targetDate())
            ->whereNull($column)
            ->get();

        foreach ($plans as $plan) {
            // 通知の保存と送信日時の記録を1つのトランザクションにまとめる。
            // どちらかだけが成功して「送ったのに未送信扱い」「送っていないのに送信済み扱い」になることを防ぐ
            DB::transaction(function () use ($plan, $timing, $column): void {
                $plan->user->notify(new ReadingPlanReminder($plan, $timing));
                $plan->update([$column => now()]);
            });
        }

        return $plans->count();
    }
}
