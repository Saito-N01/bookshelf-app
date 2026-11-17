<?php

namespace Database\Factories;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReadingPlan>
 */
class ReadingPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     * 既定では「1週間後が期日の、進行中の計画」を作る。
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'book_id' => Book::factory(),
            'target_date' => today()->addWeek(),
            'status' => ReadingPlanStatus::InProgress,
        ];
    }

    /**
     * 完了済みの計画（読了日時つき）。
     */
    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => ReadingPlanStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    /**
     * 期限切れの計画（期日は昨日）。
     */
    public function expired(): static
    {
        return $this->state(fn (): array => [
            'target_date' => today()->subDay(),
            'status' => ReadingPlanStatus::Expired,
        ]);
    }
}
