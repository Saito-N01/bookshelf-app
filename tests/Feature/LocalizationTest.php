<?php

namespace Tests\Feature;

use Tests\TestCase;

class LocalizationTest extends TestCase
{
    public function test_locale_and_timezone_are_japanese(): void
    {
        $this->assertSame('ja', config('app.locale'));
        $this->assertSame('Asia/Tokyo', config('app.timezone'));
        $this->assertSame('Asia/Tokyo', now()->timezoneName);
    }

    public function test_auth_failed_message_is_japanese(): void
    {
        $this->assertSame('ログイン情報が登録されていません。', trans('auth.failed'));
    }
}
