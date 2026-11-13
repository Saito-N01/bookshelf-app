<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Seeder;

class FavoriteSeeder extends Seeder
{
    /**
     * 各ユーザーに3〜5冊のお気に入りを登録する（固定データ）。
     */
    public function run(): void
    {
        $bookIds = Book::orderBy('id')->pluck('id')->values();

        // ユーザーのメール => お気に入りにする書籍の登録順（1〜11）
        $favorites = [
            'yamada@example.com' => [1, 6, 7],
            'suzuki@example.com' => [2, 4, 6, 8],
            'tanaka@example.com' => [3, 5, 7, 9, 10],
            'sato@example.com' => [1, 8, 11],
            'takahashi@example.com' => [2, 6, 10, 11],
        ];

        foreach ($favorites as $email => $bookNumbers) {
            User::where('email', $email)->firstOrFail()
                ->favoriteBooks()
                ->syncWithoutDetaching(
                    collect($bookNumbers)->map(fn (int $number) => $bookIds[$number - 1])->all()
                );
        }
    }
}
