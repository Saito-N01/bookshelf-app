<?php

namespace App\Enums;

/**
 * 読書計画の状態。
 * reading_plans.status には文字列（in_progress など）を保存し、
 * ReadingPlan モデルの cast でこの Enum に変換する。
 */
enum ReadingPlanStatus: string
{
    /** 進行中（登録直後の状態） */
    case InProgress = 'in_progress';

    /** 完了（「読了する」を押した状態） */
    case Completed = 'completed';

    /** 期限切れ（期日を過ぎたまま読了していない状態。日次バッチで切り替える） */
    case Expired = 'expired';

    /**
     * 画面に表示する日本語の名前。
     *
     * @return string 状態の日本語名
     */
    public function label(): string
    {
        return match ($this) {
            self::InProgress => '進行中',
            self::Completed => '完了',
            self::Expired => '期限切れ',
        };
    }

    /**
     * 状態バッジの色（Tailwind CSS のクラス）。
     * 進行中 = 青、完了 = 緑、期限切れ = 赤。
     *
     * @return string バッジに付ける背景色と文字色のクラス
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::InProgress => 'bg-blue-100 text-blue-800',
            self::Completed => 'bg-green-100 text-green-800',
            self::Expired => 'bg-red-100 text-red-800',
        };
    }
}
