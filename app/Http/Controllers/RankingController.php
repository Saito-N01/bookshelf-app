<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\View\View;

class RankingController extends Controller
{
    /**
     * 評価ランキング。レビューの平均評価が高い書籍を最大10件表示する。
     * 並び順：平均評価の高い順 → レビュー件数の多い順 → 書籍IDの小さい順。
     * レビューのない書籍は含めない。
     *
     * @return View ランキング画面
     */
    public function index(): View
    {
        $rankedBooks = Book::has('reviews')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->orderByDesc('reviews_avg_rating')
            ->orderByDesc('reviews_count')
            ->orderBy('id')
            ->limit(10)
            ->get();

        return view('ranking.index', compact('rankedBooks'));
    }
}
