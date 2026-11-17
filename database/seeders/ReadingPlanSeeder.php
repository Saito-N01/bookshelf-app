<?php

namespace Database\Seeders;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReadingPlanSeeder extends Seeder
{
    /**
     * 読書計画を6件登録する。
     * いつ実行しても同じ動作を確認できるよう、期日は「今日」を起点にして決める。
     * 主なシナリオは山田太郎に集め、鈴木花子の1件は「他人の計画は操作できない」ことの確認に使う。
     */
    public function run(): void
    {
        $userIds = User::pluck('id', 'email');
        // 同じユーザーが同じ書籍の進行中の計画を重複して持たないよう、計画ごとに別の書籍を割り当てる
        $bookIds = Book::orderBy('id')->pluck('id')->values();

        // [計画者のメール, 期日（今日から何日後か）, 状態, 読了日時（今日から何日後か。未読了は null）]
        $plans = [
            // 3日前リマインダーの対象
            ['yamada@example.com', 3, ReadingPlanStatus::InProgress, null],
            // 当日リマインダーの対象
            ['yamada@example.com', 0, ReadingPlanStatus::InProgress, null],
            // 期日を3日過ぎた進行中の計画。日次バッチで「期限切れへの変更」と「3日後の通知」の両方の対象になる
            ['yamada@example.com', -3, ReadingPlanStatus::InProgress, null],
            // リマインダーの対象外
            ['yamada@example.com', 7, ReadingPlanStatus::InProgress, null],
            // 完了済み
            ['yamada@example.com', -10, ReadingPlanStatus::Completed, -5],
            // 他のユーザーの計画（山田太郎でログインして /reading-plans/6/edit を開くと403になる）
            ['suzuki@example.com', 5, ReadingPlanStatus::InProgress, null],
        ];

        foreach ($plans as $index => [$email, $targetDays, $status, $completedDays]) {
            ReadingPlan::create([
                'user_id' => $userIds[$email],
                'book_id' => $bookIds[$index],
                'target_date' => today()->addDays($targetDays),
                'status' => $status,
                'completed_at' => $completedDays === null ? null : today()->addDays($completedDays),
            ]);
        }
    }
}
