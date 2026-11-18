<?php

namespace App\Http\Controllers;

use App\Services\ReadingReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    /**
     * マイ読書レポート。ログインユーザー本人のレビューから作った統計情報を表示する。
     *
     * @param  Request  $request  ログインユーザーを取得するためのリクエスト
     * @param  ReadingReportService  $reports  レポートの集計を担当するサービス
     * @return View マイ読書レポート画面
     */
    public function index(Request $request, ReadingReportService $reports): View
    {
        $stats = $reports->statsFor($request->user());

        return view('reports.index', compact('stats'));
    }
}
