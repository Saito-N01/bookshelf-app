<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    /**
     * レビューを32件登録する（固定データ）。
     * 各書籍に2〜4件、評価は3〜5、同じユーザーが同じ書籍に重複して投稿しない。
     */
    public function run(): void
    {
        $userIds = User::pluck('id', 'email');
        $books = Book::orderBy('id')->get()->values();

        // 書籍の登録順（1〜11） => [投稿者のメール, 評価, コメント]
        $reviews = [
            1 => [
                ['yamada@example.com', 5, '猫の視点で語られる人間観察が痛快で、何度読んでも笑えます。'],
                ['suzuki@example.com', 4, '文体は少し古めですが、ユーモアがあって読み進めやすかったです。'],
                ['tanaka@example.com', 4, '名作と呼ばれる理由が分かりました。'],
                ['sato@example.com', 3, '前半は少し退屈でしたが、後半から面白くなりました。'],
            ],
            2 => [
                ['suzuki@example.com', 5, '人間関係の考え方が変わりました。仕事でも役立っています。'],
                ['tanaka@example.com', 4, '古い本ですが、今でも通用する内容だと思います。'],
                ['takahashi@example.com', 5, '何度も読み返したくなる一冊です。'],
            ],
            3 => [
                ['yamada@example.com', 4, '変数名やコメントの書き方を見直すきっかけになりました。'],
                ['tanaka@example.com', 5, 'エンジニアなら一度は読んでおきたい本です。'],
                ['sato@example.com', 4, '具体例が多くて分かりやすかったです。'],
            ],
            4 => [
                ['suzuki@example.com', 4, '考え方の土台が整理できました。'],
                ['sato@example.com', 5, '読むたびに新しい発見があります。'],
                ['takahashi@example.com', 3, '内容は良いのですが、少しボリュームが多く感じました。'],
            ],
            5 => [
                ['yamada@example.com', 5, '主人公のまっすぐさが気持ちよく、一気に読めました。'],
                ['tanaka@example.com', 4, 'テンポが良くて読みやすい作品です。'],
                ['takahashi@example.com', 4, '学生時代に読んだ時とは違った面白さがありました。'],
            ],
            6 => [
                ['suzuki@example.com', 5, '人類の歴史を新しい視点で見られて、とても刺激的でした。'],
                ['sato@example.com', 4, 'スケールの大きな話で、知的好奇心が満たされました。'],
                ['takahashi@example.com', 5, '上下巻でも飽きずに読み切れました。'],
            ],
            7 => [
                ['yamada@example.com', 5, 'コードレビューの基準が明確になりました。'],
                ['suzuki@example.com', 4, '実務で意識したいポイントがまとまっています。'],
                ['tanaka@example.com', 3, '内容は素晴らしいですが、初心者には少し難しいです。'],
            ],
            8 => [
                ['tanaka@example.com', 5, '対話形式で読みやすく、考え方が前向きになりました。'],
                ['sato@example.com', 4, '目から鱗が落ちる思いでした。'],
                ['takahashi@example.com', 4, '人間関係に悩んでいる人におすすめしたいです。'],
            ],
            9 => [
                ['yamada@example.com', 4, '芸人の世界の熱量が伝わってくる作品でした。'],
                ['suzuki@example.com', 3, '好みが分かれそうですが、余韻の残る終わり方でした。'],
                ['sato@example.com', 4, '登場人物のやり取りが印象的でした。'],
            ],
            10 => [
                ['tanaka@example.com', 4, 'データで世界を見る大切さを実感しました。'],
                ['takahashi@example.com', 5, '思い込みに気づかされる場面が多く、勉強になりました。'],
            ],
            11 => [
                ['yamada@example.com', 3, '物流の歴史は面白いですが、やや専門的な部分もありました。'],
                ['sato@example.com', 4, '身近なものの裏側にある歴史を知ることができました。'],
            ],
        ];

        foreach ($reviews as $bookNumber => $bookReviews) {
            foreach ($bookReviews as [$email, $rating, $comment]) {
                Review::create([
                    'user_id' => $userIds[$email],
                    'book_id' => $books[$bookNumber - 1]->id,
                    'rating' => $rating,
                    'comment' => $comment,
                ]);
            }
        }
    }
}
