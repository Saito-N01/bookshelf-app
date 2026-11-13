<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Database\Seeder;

class BookSeeder extends Seeder
{
    /**
     * 書籍を登録する。登録者は最初のユーザー（山田太郎）。
     * ISBN で重複を防ぎ、ジャンルは sync で紐付ける。
     */
    public function run(): void
    {
        $user = User::first();
        $genreIds = Genre::pluck('id', 'name');

        $books = [
            [
                'title' => '吾輩は猫である',
                'author' => '夏目漱石',
                'isbn' => '9784101010014',
                'published_date' => '1905-01-01',
                'description' => '中学校の英語教師の家で飼われている猫の目を通して、明治時代の人間社会をユーモラスに描いた夏目漱石のデビュー作です。',
                'genres' => ['小説'],
            ],
            [
                'title' => '人を動かす',
                'author' => 'D・カーネギー',
                'isbn' => '9784422100524',
                'published_date' => '1936-10-01',
                'description' => '人間関係の基本原則を、豊富な実例とともに解説した自己啓発書の古典です。',
                'genres' => ['ビジネス', '自己啓発'],
            ],
            [
                'title' => 'リーダブルコード',
                'author' => 'Dustin Boswell',
                'isbn' => '9784873115658',
                'published_date' => '2012-06-23',
                'description' => '読みやすく保守しやすいコードを書くための実践的なテクニックを、具体例とともに紹介する技術書です。',
                'genres' => ['技術書'],
            ],
            [
                'title' => '7つの習慣',
                'author' => 'スティーブン・R・コヴィー',
                'isbn' => '9784863940246',
                'published_date' => '2013-08-30',
                'description' => '主体的に人生を切り開くための7つの習慣を、人格を土台にして説くビジネス・自己啓発書です。',
                'genres' => ['ビジネス', '自己啓発'],
            ],
            [
                'title' => '坊っちゃん',
                'author' => '夏目漱石',
                'isbn' => '9784101010021',
                'published_date' => '1906-04-01',
                'description' => '無鉄砲で正義感の強い青年が、四国の中学校に数学教師として赴任して巻き起こす騒動を描いた痛快な青春小説です。',
                'genres' => ['小説'],
            ],
            [
                'title' => 'サピエンス全史',
                'author' => 'ユヴァル・ノア・ハラリ',
                'isbn' => '9784309226712',
                'published_date' => '2016-09-08',
                'description' => '認知革命・農業革命・科学革命を軸に、人類の歴史を大きな視点から読み解く一冊です。',
                'genres' => ['歴史', '科学'],
            ],
            [
                'title' => 'Clean Code',
                'author' => 'Robert C. Martin',
                'isbn' => '9784048930598',
                'published_date' => '2017-12-18',
                'description' => '良いコードとは何かを、命名・関数・テストなどの観点から具体的に解説するソフトウェア開発の定番書です。',
                'genres' => ['技術書'],
            ],
            [
                'title' => '嫌われる勇気',
                'author' => '岸見一郎・古賀史健',
                'isbn' => '9784478025819',
                'published_date' => '2013-12-13',
                'description' => 'アドラー心理学をもとに、青年と哲学者の対話を通じて幸せに生きる方法を考える一冊です。',
                'genres' => ['自己啓発'],
            ],
            [
                'title' => '火花',
                'author' => '又吉直樹',
                'isbn' => '9784163902302',
                'published_date' => '2015-03-11',
                'description' => '売れない若手漫才師と、彼が師と仰ぐ先輩芸人との交流を描いた芥川賞受賞作です。',
                'genres' => ['小説'],
            ],
            [
                'title' => 'FACTFULNESS',
                'author' => 'ハンス・ロスリング',
                'isbn' => '9784822289607',
                'published_date' => '2019-01-11',
                'description' => '思い込みを避け、データに基づいて世界を正しく見るための習慣を紹介する一冊です。',
                'genres' => ['ビジネス', '科学'],
            ],
            [
                'title' => 'コンテナ物語',
                'author' => 'マルク・レビンソン',
                'isbn' => '9784822251468',
                'published_date' => '2007-01-18',
                'description' => 'コンテナの発明が世界の物流と経済をどのように変えたかを描くノンフィクションです。',
                'genres' => ['ビジネス', '歴史'],
            ],
        ];

        foreach ($books as $index => $data) {
            $book = Book::firstOrCreate(
                ['isbn' => $data['isbn']],
                [
                    'user_id' => $user->id,
                    'title' => $data['title'],
                    'author' => $data['author'],
                    'published_date' => $data['published_date'],
                    'description' => $data['description'],
                    'image_url' => 'https://placehold.co/200x300/e2e8f0/475569?text='.($index + 1),
                ]
            );

            $book->genres()->sync($genreIds->only($data['genres'])->values());
        }
    }
}
