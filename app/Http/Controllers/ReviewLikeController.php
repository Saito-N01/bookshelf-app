<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReviewLikeController extends Controller
{
    /**
     * レビューへのいいねの追加・解除を切り替える。
     * 自分のレビューにはいいねできない（ReviewPolicy@like で403）。
     *
     * @param  Request  $request  ログインユーザーを取得するためのリクエスト
     * @param  Review  $review  いいねを切り替えるレビュー
     * @return RedirectResponse 元の画面へのリダイレクト
     */
    public function toggle(Request $request, Review $review): RedirectResponse
    {
        $this->authorize('like', $review);

        $request->user()->likedReviews()->toggle($review);

        return back();
    }
}
