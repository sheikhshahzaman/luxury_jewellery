<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeExperienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flushCache();
    }

    public function test_home_opens_with_the_scroll_film_and_its_reasons_to_buy(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('data-story', $html);
        $this->assertStringContainsString('data-story-stage', $html);
        $this->assertStringContainsString('class="anx-poster"', $html);
        $this->assertSame(6, substr_count($html, 'data-anx-chapter'));
        $this->assertSame(1, substr_count($html, '<h1'));
        foreach (['Made your way', "Today's gold rate, in the open", 'Pay the way you like', 'Delivered and tracked', 'Your metal', 'Cash on delivery', 'Order tracking'] as $text) {
            $this->assertStringContainsString(e($text), $html);
        }
        // The collection has no stones: no diamond wording anywhere in the story.
        $this->assertDoesNotMatchRegularExpression('/diamond|brilliant|gemstone/i', substr($html, strpos($html, 'data-story'), 20000));
        $this->assertStringNotContainsString('data-anatomy', $html);
    }

    public function test_film_frames_are_listed_for_the_player(): void
    {
        $frames = hero_frames();
        if ($frames === []) {
            $this->markTestSkipped('No film frames in public/media/hero.');
        }

        $html = $this->get('/')->assertOk()->getContent();
        foreach ($frames as $set) {
            $this->assertStringContainsString(e(json_encode($set['base'])), $html);
            $this->assertFileExists(public_path('media/hero/'.basename($set['base']).'/'.sprintf('%03d', $set['count']).'.webp'));
        }
        $this->assertStringContainsString('fetchpriority="high"', $html);
    }

    public function test_story_copy_is_editable_in_settings(): void
    {
        Setting::set('story_2_title', 'Fair gold prices');

        $this->get('/')->assertSee('Fair gold prices');
    }

    public function test_rename_migration_turns_lux_into_lax_and_leaves_custom_names_alone(): void
    {
        Setting::set('store_name', 'Lux Jewellers');
        BankAccount::create(['type' => 'bank', 'bank_name' => 'Meezan', 'account_name' => 'Lux Jewellers (Pvt) Ltd', 'account_number' => '1']);
        BankAccount::create(['type' => 'bank', 'bank_name' => 'HBL', 'account_name' => 'Noor Traders', 'account_number' => '2']);

        (require database_path('migrations/2026_10_08_000001_rename_store_to_lax_jewellers.php'))->up();

        Setting::flushCache();
        $this->assertSame('Lax Jewellers', setting('store_name'));
        $this->assertSame(['Lax Jewellers (Pvt) Ltd', 'Noor Traders'], BankAccount::orderBy('id')->pluck('account_name')->all());

        // A store name the admin has already changed is not touched.
        Setting::set('store_name', 'Something Else');
        (require database_path('migrations/2026_10_08_000001_rename_store_to_lax_jewellers.php'))->up();
        Setting::flushCache();
        $this->assertSame('Something Else', setting('store_name'));
    }
}
