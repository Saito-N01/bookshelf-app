<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'author' => $this->author,
            'isbn' => $this->isbn,
            'published_date' => $this->published_date?->format('Y-m-d'),
            'description' => $this->description,
            'image_url' => $this->image_url,
            'user_id' => $this->user_id,
            'genres' => GenreResource::collection($this->whenLoaded('genres')),
            // 平均評価は小数第1位に丸める。レビューがない書籍は null
            'average_rating' => $this->reviews_avg_rating === null
                ? null
                : round((float) $this->reviews_avg_rating, 1),
            'reviews_count' => (int) $this->reviews_count,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            // レビューは、読み込まれているとき（詳細API）だけ出力する
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
        ];
    }
}
