<?php

namespace Tests\Unit\Models;

use App\Models\Book;
use App\Models\Genre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenreTest extends TestCase
{
    use RefreshDatabase;

    public function test_genre_has_books(): void
    {
        $genre = Genre::factory()->create();
        $books = Book::factory()->count(2)->create();
        $genre->books()->attach($books);

        $this->assertCount(2, $genre->books);
    }

    public function test_with_count_returns_number_of_books(): void
    {
        $genre = Genre::factory()->create();
        $genre->books()->attach(Book::factory()->count(3)->create());

        $result = Genre::withCount('books')->find($genre->id);

        $this->assertSame(3, $result->books_count);
    }

    public function test_factory_creates_genres_with_unique_names(): void
    {
        $genres = Genre::factory()->count(5)->create();

        $this->assertCount(5, $genres->pluck('name')->unique());
    }
}
