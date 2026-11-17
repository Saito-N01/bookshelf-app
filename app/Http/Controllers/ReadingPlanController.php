<?php

namespace App\Http\Controllers;

use App\Enums\ReadingPlanStatus;
use App\Http\Requests\StoreReadingPlanRequest;
use App\Http\Requests\UpdateReadingPlanRequest;
use App\Models\ReadingPlan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReadingPlanController extends Controller
{
    /**
     * 読書計画一覧。ログインユーザーの計画を、期日の近い順に表示する。
     * status を指定すると、その状態の計画だけに絞り込む。不正な値は「すべて」として扱う。
     *
     * @param  Request  $request  ログインユーザーと、絞り込み条件（status）を持つリクエスト
     * @return View 読書計画一覧画面
     */
    public function index(Request $request): View
    {
        // tryFrom は、Enum にない値のとき例外ではなく null を返す
        $statusInput = $request->query('status');
        $status = is_string($statusInput) ? ReadingPlanStatus::tryFrom($statusInput) : null;

        $readingPlans = $request->user()
            ->readingPlans()
            ->with('book')
            ->when($status !== null, fn (Builder $query): Builder => $query->where('status', $status))
            ->orderBy('target_date')
            ->orderBy('id')
            ->get();

        // 絞り込みプルダウンで選択中にする値（「すべて」のときは空文字）
        $currentStatus = $status?->value ?? '';

        return view('reading-plans.index', compact('readingPlans', 'currentStatus'));
    }

    /**
     * 読書計画作成画面。書籍のプルダウンには、自分が登録した書籍だけを表示する。
     *
     * @param  Request  $request  ログインユーザーを取得するためのリクエスト
     * @return View 読書計画作成画面
     */
    public function create(Request $request): View
    {
        $books = $request->user()->books()->orderBy('id')->get();

        return view('reading-plans.create', compact('books'));
    }

    /**
     * 読書計画を登録する。計画者はログインユーザーで、状態は「進行中」から始まる。
     *
     * @param  StoreReadingPlanRequest  $request  バリデーション済みの入力値
     * @return RedirectResponse 読書計画一覧へのリダイレクト
     */
    public function store(StoreReadingPlanRequest $request): RedirectResponse
    {
        $request->user()->readingPlans()->create($request->validated());

        return redirect()->route('reading-plans.index')->with('success', '読書計画を登録しました。');
    }

    /**
     * 読書計画編集画面。計画者本人の、完了していない計画だけを表示できる。
     *
     * @param  ReadingPlan  $plan  編集する読書計画
     * @return View 読書計画編集画面
     */
    public function edit(ReadingPlan $plan): View
    {
        $this->authorize('update', $plan);

        $plan->load('book');

        return view('reading-plans.edit', ['readingPlan' => $plan]);
    }

    /**
     * 読書計画の期日を更新する。変更できるかどうかは UpdateReadingPlanRequest で判定する。
     * - 期日が変わった場合は、リマインダーの送信日時をリセットする（新しい期日に合わせて、もう一度通知するため）
     * - 期限切れの計画は、進行中に戻す
     *
     * @param  UpdateReadingPlanRequest  $request  バリデーション済みの入力値
     * @param  ReadingPlan  $plan  更新する読書計画
     * @return RedirectResponse 読書計画一覧へのリダイレクト
     */
    public function update(UpdateReadingPlanRequest $request, ReadingPlan $plan): RedirectResponse
    {
        $plan->target_date = $request->validated('target_date');

        if ($plan->isDirty('target_date')) {
            $plan->notified_three_days_before_at = null;
            $plan->notified_on_due_at = null;
            $plan->notified_three_days_after_at = null;
        }

        if ($plan->status === ReadingPlanStatus::Expired) {
            $plan->status = ReadingPlanStatus::InProgress;
        }

        $plan->save();

        return redirect()->route('reading-plans.index')->with('success', '読書計画を更新しました。');
    }

    /**
     * 「読了する」。計画を完了にして、完了日時を記録する。
     * 対象は完了していない計画（進行中・期限切れ）で、完了済みの計画は403になる。
     *
     * @param  ReadingPlan  $plan  読了にする読書計画
     * @return RedirectResponse 読書計画一覧へのリダイレクト
     */
    public function complete(ReadingPlan $plan): RedirectResponse
    {
        $this->authorize('update', $plan);

        $plan->update([
            'status' => ReadingPlanStatus::Completed,
            'completed_at' => now(),
        ]);

        return redirect()->route('reading-plans.index')->with('success', '読了しました。');
    }

    /**
     * 読書計画を削除する。計画者本人だけが削除できる。
     *
     * @param  ReadingPlan  $plan  削除する読書計画
     * @return RedirectResponse 読書計画一覧へのリダイレクト
     */
    public function destroy(ReadingPlan $plan): RedirectResponse
    {
        $this->authorize('delete', $plan);

        $plan->delete();

        return redirect()->route('reading-plans.index')->with('success', '読書計画を削除しました。');
    }
}
