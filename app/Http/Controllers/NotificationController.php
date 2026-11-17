<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    /**
     * 通知一覧。ログインユーザーの通知を、新しい順にすべて表示する（既読の通知も残す）。
     *
     * @param  Request  $request  ログインユーザーを取得するためのリクエスト
     * @return View 通知一覧画面
     */
    public function index(Request $request): View
    {
        // notifications リレーションは、Laravel が新しい順（created_at の降順）で返す
        $notifications = $request->user()->notifications()->get();

        return view('notifications.index', compact('notifications'));
    }

    /**
     * 通知を既読にして、元の画面（通知一覧）に戻る。
     * 自分の通知の中から探すため、他人の通知や存在しないIDは404になる。
     *
     * @param  Request  $request  ログインユーザーを取得するためのリクエスト
     * @param  string  $id  既読にする通知のID（UUID）
     * @return RedirectResponse 元の画面へのリダイレクト
     */
    public function read(Request $request, string $id): RedirectResponse
    {
        $request->user()->notifications()->findOrFail($id)->markAsRead();

        return back();
    }
}
