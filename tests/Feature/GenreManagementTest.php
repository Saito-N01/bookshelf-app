<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenreManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    // ---------- ゲスト ----------

    public function test_guest_is_redirected_to_login_from_all_genre_routes(): void
    {
        $genre = Genre::factory()->create(['name' => '元のジャンル']);

        $this->get('/genres')->assertRedirect('/login');
        $this->get('/genres/create')->assertRedirect('/login');
        $this->get('/genres/'.$genre->id)->assertRedirect('/login');
        $this->get('/genres/'.$genre->id.'/edit')->assertRedirect('/login');
        $this->post('/genres', ['name' => '新しいジャンル'])->assertRedirect('/login');
        $this->put('/genres/'.$genre->id, ['name' => '変更後'])->assertRedirect('/login');
        $this->delete('/genres/'.$genre->id)->assertRedirect('/login');

        $this->assertDatabaseCount('genres', 1);
        $this->assertDatabaseHas('genres', ['id' => $genre->id, 'name' => '元のジャンル']);
    }

    // ---------- 一覧 ----------

    public function test_genre_list_shows_names_with_book_counts(): void
    {
        $genre = Genre::factory()->create(['name' => '書籍ありジャンル']);
        $genre->books()->attach(Book::factory()->count(2)->create());
        Genre::factory()->create(['name' => '書籍なしジャンル']);

        $response = $this->actingAs($this->user)->get('/genres');

        $response->assertOk()
            ->assertSeeInOrder(['書籍ありジャンル', '2冊', '書籍なしジャンル', '0冊']);
    }

    public function test_genre_list_shows_flash_messages(): void
    {
        $this->actingAs($this->user)
            ->withSession(['success' => 'ジャンルを登録しました。'])
            ->get('/genres')
            ->assertSee('ジャンルを登録しました。');

        $this->actingAs($this->user)
            ->withSession(['error' => '書籍が紐づいているため、このジャンルは削除できません。'])
            ->get('/genres')
            ->assertSee('書籍が紐づいているため、このジャンルは削除できません。');
    }

    // ---------- 詳細 ----------

    public function test_genre_detail_shows_only_its_books_paginated_by_ten(): void
    {
        $genre = Genre::factory()->create();
        $genre->books()->attach(Book::factory()->count(11)->create());
        Book::factory()->create(['title' => '別ジャンルの書籍']);

        $response = $this->actingAs($this->user)->get('/genres/'.$genre->id);

        $response->assertOk()
            ->assertSee($genre->name)
            ->assertDontSee('別ジャンルの書籍')
            ->assertViewHas('books', fn ($books) => $books->count() === 10 && $books->total() === 11);

        $this->actingAs($this->user)
            ->get('/genres/'.$genre->id.'?page=2')
            ->assertViewHas('books', fn ($books) => $books->count() === 1);
    }

    public function test_genre_detail_returns_404_for_nonexistent_id(): void
    {
        $this->actingAs($this->user)->get('/genres/999')->assertNotFound();
    }

    // ---------- 登録 ----------

    public function test_authenticated_user_can_view_create_screen(): void
    {
        $this->actingAs($this->user)->get('/genres/create')->assertOk();
    }

    public function test_user_can_store_genre(): void
    {
        $response = $this->actingAs($this->user)->post('/genres', ['name' => '新しいジャンル']);

        $response->assertRedirect('/genres');
        $response->assertSessionHas('success', 'ジャンルを登録しました。');
        $this->assertDatabaseHas('genres', ['name' => '新しいジャンル']);
    }

    public function test_store_fails_when_name_is_empty(): void
    {
        $response = $this->actingAs($this->user)->from('/genres/create')->post('/genres', ['name' => '']);

        $response->assertRedirect('/genres/create');
        $response->assertSessionHasErrors(['name' => 'ジャンル名は必須です。']);
        $this->assertDatabaseCount('genres', 0);
    }

    public function test_store_fails_when_name_is_too_long(): void
    {
        $response = $this->actingAs($this->user)->post('/genres', ['name' => str_repeat('あ', 256)]);

        $response->assertSessionHasErrors(['name' => 'ジャンル名は255文字以内で入力してください。']);
        $this->assertDatabaseCount('genres', 0);
    }

    public function test_store_fails_when_name_is_already_used(): void
    {
        Genre::factory()->create(['name' => '登録済みジャンル']);

        $response = $this->actingAs($this->user)->post('/genres', ['name' => '登録済みジャンル']);

        $response->assertSessionHasErrors(['name' => 'そのジャンル名は既に使用されています。']);
        $this->assertDatabaseCount('genres', 1);
    }

    // ---------- 更新 ----------

    public function test_edit_screen_shows_current_name(): void
    {
        $genre = Genre::factory()->create(['name' => '編集前のジャンル']);

        $this->actingAs($this->user)
            ->get('/genres/'.$genre->id.'/edit')
            ->assertOk()
            ->assertSee('value="編集前のジャンル"', false);
    }

    public function test_user_can_update_genre(): void
    {
        $genre = Genre::factory()->create(['name' => '変更前']);

        $response = $this->actingAs($this->user)->put('/genres/'.$genre->id, ['name' => '変更後']);

        $response->assertRedirect('/genres');
        $response->assertSessionHas('success', 'ジャンルを更新しました。');
        $this->assertDatabaseHas('genres', ['id' => $genre->id, 'name' => '変更後']);
    }

    public function test_update_succeeds_with_its_own_current_name(): void
    {
        $genre = Genre::factory()->create(['name' => '同じ名前']);

        $response = $this->actingAs($this->user)->put('/genres/'.$genre->id, ['name' => '同じ名前']);

        $response->assertRedirect('/genres');
        $response->assertSessionHasNoErrors();
    }

    public function test_update_fails_when_name_is_used_by_another_genre(): void
    {
        Genre::factory()->create(['name' => '他のジャンル']);
        $genre = Genre::factory()->create(['name' => '変更前']);

        $response = $this->actingAs($this->user)
            ->from('/genres/'.$genre->id.'/edit')
            ->put('/genres/'.$genre->id, ['name' => '他のジャンル']);

        $response->assertRedirect('/genres/'.$genre->id.'/edit');
        $response->assertSessionHasErrors(['name' => 'そのジャンル名は既に使用されています。']);
        $this->assertDatabaseHas('genres', ['id' => $genre->id, 'name' => '変更前']);
    }

    public function test_update_fails_when_name_is_empty(): void
    {
        $genre = Genre::factory()->create(['name' => '変更前']);

        $response = $this->actingAs($this->user)->put('/genres/'.$genre->id, ['name' => '']);

        $response->assertSessionHasErrors(['name' => 'ジャンル名は必須です。']);
        $this->assertDatabaseHas('genres', ['id' => $genre->id, 'name' => '変更前']);
    }

    // ---------- 削除 ----------

    public function test_user_can_delete_genre_without_books(): void
    {
        $genre = Genre::factory()->create();

        $response = $this->actingAs($this->user)->delete('/genres/'.$genre->id);

        $response->assertRedirect('/genres');
        $response->assertSessionHas('success', 'ジャンルを削除しました。');
        $this->assertDatabaseMissing('genres', ['id' => $genre->id]);
    }

    public function test_genre_with_books_cannot_be_deleted(): void
    {
        $genre = Genre::factory()->create();
        $book = Book::factory()->create();
        $genre->books()->attach($book);

        $response = $this->actingAs($this->user)->delete('/genres/'.$genre->id);

        $response->assertRedirect('/genres');
        $response->assertSessionHas('error', '書籍が紐づいているため、このジャンルは削除できません。');
        $response->assertSessionMissing('success');
        $this->assertDatabaseHas('genres', ['id' => $genre->id]);
        $this->assertDatabaseHas('book_genre', ['book_id' => $book->id, 'genre_id' => $genre->id]);
    }
}
