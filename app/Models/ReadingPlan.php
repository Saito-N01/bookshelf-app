<?php

namespace App\Models;

use App\Enums\ReadingPlanStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReadingPlan extends Model
{
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'book_id',
        'target_date',
        'status',
        'completed_at',
        'notified_three_days_before_at',
        'notified_on_due_at',
        'notified_three_days_after_at',
    ];

    /**
     * 新しく作るモデルの初期値。DBの default（in_progress = ReadingPlanStatus::InProgress）と同じ値にして、
     * status を指定せずに作った直後のモデルでも、status を Enum として参照できるようにする。
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'in_progress',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'target_date' => 'date',
        // DBの文字列（in_progress など）と ReadingPlanStatus を自動で変換する
        'status' => ReadingPlanStatus::class,
        'completed_at' => 'datetime',
        'notified_three_days_before_at' => 'datetime',
        'notified_on_due_at' => 'datetime',
        'notified_three_days_after_at' => 'datetime',
    ];

    /**
     * 計画を立てたユーザー。
     *
     * @return BelongsTo 計画者（User）へのリレーション
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 計画の対象書籍。
     *
     * @return BelongsTo 対象書籍（Book）へのリレーション
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
