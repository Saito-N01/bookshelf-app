<?php

namespace App\Services;

use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\Http;

/**
 * Google Books API から書籍情報を取得する。
 * 外部APIのレスポンスを、アプリのフォームで使う項目名に変換する役割を持つ。
 */
class GoogleBooksService
{
    /**
     * ISBN で書籍を検索し、登録フォームに入力する項目を返す。
     *
     * @param  string  $isbn  13桁のISBN
     * @return array{title: ?string, author: ?string, published_date: ?string, description: ?string, image_url: ?string}|null 該当する書籍がない場合は null
     *
     * @throws HttpClientException 接続できない場合や、外部APIがエラーを返した場合
     */
    public function findByIsbn(string $isbn): ?array
    {
        // APIキーは設定されているときだけ送る（array_filter で null を取り除く）
        $response = Http::timeout(10)
            ->get(config('services.google_books.url'), array_filter([
                'q' => 'isbn:'.$isbn,
                'key' => config('services.google_books.key'),
            ]))
            ->throw();

        $volume = $response->json('items.0.volumeInfo');

        if ($volume === null) {
            return null;
        }

        return [
            'title' => $volume['title'] ?? null,
            // 著者が複数いる場合は「、」でつなげて1つの文字列にする
            'author' => isset($volume['authors']) ? implode('、', $volume['authors']) : null,
            'published_date' => $this->normalizeDate($volume['publishedDate'] ?? null),
            'description' => $volume['description'] ?? null,
            'image_url' => $volume['imageLinks']['thumbnail'] ?? null,
        ];
    }

    /**
     * 出版日を Y-m-d 形式にそろえる。
     * Google Books は「2012」「2012-06」のような不完全な日付を返すことがあり、その場合は null にする。
     *
     * @param  string|null  $date  Google Books の publishedDate
     * @return string|null 年月日がそろった実在する日付のときだけ、その日付
     */
    private function normalizeDate(?string $date): ?string
    {
        if ($date === null || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $matches)) {
            return null;
        }

        return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]) ? $date : null;
    }
}
