<?php

use App\Contracts\MuxClient;
use App\Models\AiPreset;
use App\Models\AiProvider;
use App\Models\Category;
use App\Models\Dish;
use App\Models\DishPhoto;
use App\Models\GenerationBalance;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\Video;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Fakes\FakeMuxClient;
use Tests\Fakes\FakeVideoGenerationProvider;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        $this->withoutVite();

        // Livewire keeps "a component rendered this request" in static state; reset it so
        // a previous test's component never leaks asset injection into a non-Livewire page.
        app('livewire')->flushState();
    })
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Seed every lookup table and the baseline plans (no demo users).
 */
function seedReferenceData(): void
{
    test()->seed(ReferenceDataSeeder::class);
}

/**
 * A real 1×1 PNG upload that does not need the GD extension.
 */
function fakeImage(string $name = 'photo.png'): UploadedFile
{
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

    return UploadedFile::fake()->createWithContent($name, $png);
}

/**
 * Bind the fake AI provider + fake Mux and create an active preset using it.
 */
function fakeVideoPipeline(): FakeMuxClient
{
    FakeVideoGenerationProvider::reset();
    config()->set('ai.providers.fake', FakeVideoGenerationProvider::class);

    $mux = new FakeMuxClient;
    app()->instance(MuxClient::class, $mux);

    $provider = AiProvider::factory()->create(['slug' => 'fake', 'name' => 'Fake']);
    AiPreset::factory()->create(['provider_id' => $provider->id]);

    return $mux;
}

/**
 * A dono with a restaurant, one category and one dish with photos.
 *
 * @return array{0: User, 1: Dish}
 */
function ownerWithDish(int $photos = 1, int $monthly = 0, int $addon = 5): array
{
    $dono = User::factory()->create();
    $restaurant = $dono->restaurant;

    GenerationBalance::updateOrCreate(
        ['restaurant_id' => $restaurant->id],
        ['monthly_balance' => $monthly, 'addon_balance' => $addon],
    );

    $dish = Dish::factory()->create(['restaurant_id' => $restaurant->id]);
    DishPhoto::factory()->count($photos)->sequence(fn ($sequence) => ['display_order' => $sequence->index])->create(['dish_id' => $dish->id]);

    return [$dono, $dish->fresh()];
}

/**
 * A feed-eligible dish: approved active video, visible category.
 */
function feedDish(Restaurant $restaurant, array $attributes = [], ?Category $category = null): Dish
{
    $category ??= Category::factory()->create(['restaurant_id' => $restaurant->id]);
    $dish = Dish::factory()->create(array_merge([
        'restaurant_id' => $restaurant->id,
        'category_id' => $category->id,
    ], $attributes));

    $video = Video::factory()->approved()->create(['dish_id' => $dish->id]);
    $dish->update(['active_video_id' => $video->id]);

    return $dish->fresh(['activeVideo']);
}

/**
 * Post a Stripe webhook event to Cashier's endpoint (no signing secret in tests).
 *
 * @param  array<string, mixed>  $object
 */
function stripeWebhook(string $type, array $object): TestResponse
{
    return test()->postJson(route('cashier.webhook'), [
        'id' => 'evt_'.Str::random(10),
        'type' => $type,
        'data' => ['object' => $object],
    ]);
}

/**
 * A customer.subscription.* object for the given plan price.
 *
 * @return array<string, mixed>
 */
function stripeSubscription(string $customer, string $priceId, string $status = 'active', string $id = 'sub_1', array $extra = []): array
{
    return array_merge([
        'id' => $id,
        'object' => 'subscription',
        'customer' => $customer,
        'status' => $status,
        'metadata' => ['type' => 'default'],
        'items' => ['data' => [[
            'id' => 'si_'.$id,
            'price' => ['id' => $priceId, 'product' => 'prod_x'],
            'quantity' => 1,
        ]]],
    ], $extra);
}
