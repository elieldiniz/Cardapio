<?php

use App\Actions\Menu\SavePromoBanner;
use App\Models\Category;
use App\Models\PromoBanner;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/**
 * RF-16 — the promotional banner.
 */
beforeEach(function () {
    seedReferenceData();
    Storage::fake('public');
    $this->dono = User::factory()->create();
    $this->store = $this->dono->restaurant;
    $this->category = Category::factory()->create(['restaurant_id' => $this->store->id]);
    feedDish($this->store, ['name' => 'Feijoada'], $this->category);
});

it('validates the title and text limits', function () {
    expect(fn () => app(SavePromoBanner::class)->handle($this->store, ['title' => str_repeat('a', 71)]))->toThrow(ValidationException::class);
    expect(fn () => app(SavePromoBanner::class)->handle($this->store, ['title' => 'Ok', 'text' => str_repeat('a', 141)]))->toThrow(ValidationException::class);
    expect(fn () => app(SavePromoBanner::class)->handle($this->store, ['title' => '']))->toThrow(ValidationException::class);

    expect(app(SavePromoBanner::class)->handle($this->store, ['title' => str_repeat('a', 70), 'text' => str_repeat('b', 140)])->exists)->toBeTrue()
        ->and(PromoBanner::count())->toBe(1);
});

it('keeps one banner per store and replaces the photo deleting the old file', function () {
    $first = app(SavePromoBanner::class)->handle($this->store, ['title' => 'Um'], UploadedFile::fake()->image('a.jpg'));
    $old = $first->image_path;
    Storage::disk('public')->assertExists($old);

    $second = app(SavePromoBanner::class)->handle($this->store, ['title' => 'Dois'], UploadedFile::fake()->image('b.png'));

    expect(PromoBanner::count())->toBe(1)->and($second->title)->toBe('Dois')->and($second->image_path)->not->toBe($old);
    Storage::disk('public')->assertMissing($old);
    Storage::disk('public')->assertExists($second->image_path);
});

it('rejects a file that is not an image', function () {
    expect(fn () => app(SavePromoBanner::class)->handle($this->store, ['title' => 'Ok'], UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')))->toThrow(ValidationException::class);
});

it('removes the banner and its photo', function () {
    $banner = app(SavePromoBanner::class)->handle($this->store, ['title' => 'Um'], UploadedFile::fake()->image('a.jpg'));

    app(SavePromoBanner::class)->remove($this->store);

    expect(PromoBanner::count())->toBe(0);
    Storage::disk('public')->assertMissing($banner->image_path);
});

it('shows the banner on the cardápio and hides it the day after its last day', function () {
    app(SavePromoBanner::class)->handle($this->store, ['title' => 'Sábado é dia de feijoada!', 'text' => '15% no kit.', 'ends_on' => '2026-11-07']);

    $this->travelTo('2026-11-07 20:00:00'); // 16:00 in Porto Velho, the last day
    $this->get(route('feed.show', $this->store))->assertOk()->assertSee('Sábado é dia de feijoada!')->assertSee('15% no kit.')->assertSeeHtml('data-promo-banner');

    $this->travelTo('2026-11-08 05:00:00'); // 01:00 of the next day in Porto Velho
    $this->get(route('feed.show', $this->store))->assertOk()->assertDontSee('Sábado é dia de feijoada!')->assertDontSeeHtml('data-promo-banner');
});

it('never shows a switched off banner and offers "Ver ofertas" only with products on sale', function () {
    app(SavePromoBanner::class)->handle($this->store, ['title' => 'Promo', 'is_active' => false]);
    $this->get(route('feed.show', $this->store))->assertDontSeeHtml('data-promo-banner');

    app(SavePromoBanner::class)->handle($this->store, ['title' => 'Promo', 'is_active' => true]);
    $this->get(route('feed.show', $this->store))->assertSeeHtml('data-promo-banner')->assertDontSeeHtml('data-show-offers');

    $this->store->dishes()->first()->update(['price' => '40.00', 'promo_price' => '30.00']);
    $this->get(route('feed.show', $this->store))->assertSeeHtml('data-show-offers');
});

it('edits the banner from the product list', function () {
    Livewire::actingAs($this->dono)->test('pages::panel.dishes')
        ->assertSee('Banner de promoção')->assertSee('Criar banner')
        ->call('openBanner')->assertSet('showBanner', true)
        ->set('bannerTitle', 'Sábado é dia de feijoada!')->set('bannerText', 'Kit com 15%')->set('bannerEnds', '2026-12-01')
        ->call('saveBanner')->assertHasNoErrors()->assertSet('showBanner', false)->assertSee('No ar:')->assertSee('Editar banner');

    expect(PromoBanner::sole())->title->toBe('Sábado é dia de feijoada!')->and(PromoBanner::sole()->ends_on->toDateString())->toBe('2026-12-01');
});

it('shows the banner errors in the editor and can remove it', function () {
    Livewire::actingAs($this->dono)->test('pages::panel.dishes')
        ->call('openBanner')->set('bannerTitle', str_repeat('a', 71))->call('saveBanner')->assertHasErrors('bannerTitle');

    expect(PromoBanner::count())->toBe(0);

    app(SavePromoBanner::class)->handle($this->store, ['title' => 'Promo']);

    Livewire::actingAs($this->dono)->test('pages::panel.dishes')->call('removeBanner');

    expect(PromoBanner::count())->toBe(0);
});
