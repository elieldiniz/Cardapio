<?php

use App\Models\Category;
use App\Models\Dish;
use App\Models\DishStatus;
use App\Models\Restaurant;
use App\Models\Video;
use App\Support\MuxUrls;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    seedReferenceData();
    $this->restaurant = Restaurant::factory()->create(['slug' => 'fumaca']);
});

test('the feed route requires no authentication', function () {
    feedDish($this->restaurant, ['name' => 'Burger Clássico']);

    $this->assertGuest();
    $this->get('/r/fumaca')->assertOk()->assertSee('Burger Clássico');
});

test('the initial response embeds the first dishs cover and video url', function () {
    $burgers = Category::factory()->create(['restaurant_id' => $this->restaurant->id, 'display_order' => 0]);
    $first = feedDish($this->restaurant, ['display_order' => 0], $burgers);
    feedDish($this->restaurant, ['display_order' => 1], $burgers);

    $playback = $first->activeVideo->mux_playback_id;
    $html = $this->get(route('feed.show', $this->restaurant))->getContent();

    // The opening grid shows the cover; the feed markup already carries the video URL,
    // so a tapped dish starts playing without another request.
    expect($html)
        ->toContain('<link rel="preload" as="image" href="'.$first->activeVideo->cover_path.'"')
        ->toContain('<img class="grid-item__image" src="'.$first->activeVideo->cover_path.'"')
        ->toContain('data-video-src="'.MuxUrls::mp4($playback).'"')
        ->toContain('poster="'.$first->activeVideo->cover_path.'"');

    // Nothing autoplays behind the grid.
    expect(substr_count($html, ' autoplay'))->toBe(0);
});

test('a dish without an approved active video never appears in the feed', function () {
    $category = Category::factory()->create(['restaurant_id' => $this->restaurant->id]);
    feedDish($this->restaurant, ['name' => 'Com vídeo'], $category);

    $withoutVideo = Dish::factory()->create(['restaurant_id' => $this->restaurant->id, 'category_id' => $category->id, 'name' => 'Sem vídeo']);
    $pending = Dish::factory()->create(['restaurant_id' => $this->restaurant->id, 'category_id' => $category->id, 'name' => 'Vídeo pendente']);
    $pendingVideo = Video::factory()->awaitingApproval()->create(['dish_id' => $pending->id]);
    $pending->update(['active_video_id' => $pendingVideo->id]);

    $this->get(route('feed.show', $this->restaurant))
        ->assertSee('Com vídeo')
        ->assertDontSee('Sem vídeo')
        ->assertDontSee('Vídeo pendente');
});

test('a dish in a hidden category never appears in the feed', function () {
    $hidden = Category::factory()->hidden()->create(['restaurant_id' => $this->restaurant->id, 'name' => 'Secreta']);
    feedDish($this->restaurant, ['name' => 'Prato escondido'], $hidden);
    feedDish($this->restaurant, ['name' => 'Prato visível']);

    $this->get(route('feed.show', $this->restaurant))
        ->assertSee('Prato visível')
        ->assertDontSee('Prato escondido')
        ->assertDontSee('Secreta');
});

it('orders categories and dishes and keeps sold out dishes flagged', function () {
    $second = Category::factory()->create(['restaurant_id' => $this->restaurant->id, 'name' => 'Bebidas', 'display_order' => 1]);
    $first = Category::factory()->create(['restaurant_id' => $this->restaurant->id, 'name' => 'Burgers', 'display_order' => 0]);
    feedDish($this->restaurant, ['name' => 'B-dois', 'display_order' => 2], $first);
    feedDish($this->restaurant, ['name' => 'B-um', 'display_order' => 1, 'status_id' => DishStatus::idFor('esgotado')], $first);
    feedDish($this->restaurant, ['name' => 'Limonada'], $second);

    $html = $this->get(route('feed.show', $this->restaurant))->getContent();
    [$grid, $feed] = explode('data-feed-list', $html, 2);

    // The grid lists every category's dishes in menu order, sold-out ones flagged.
    expect($grid)->toMatch('/Burgers.*Bebidas/s')
        ->toMatch('/grid-item__sold-out.*B-um.*B-dois.*Limonada/s');

    // The feed is pre-rendered with the first category only; others load on demand.
    expect($feed)->toMatch('/Esgotado.*B-um.*B-dois/s')
        ->not->toContain('Limonada');
});

it('shows the made-with branding only on plans that keep it', function () {
    feedDish($this->restaurant);

    $this->get(route('feed.show', $this->restaurant))->assertSee('feito com');

    $this->restaurant->plan->update(['removes_branding' => true]);

    $this->get(route('feed.show', $this->restaurant))->assertDontSee('feito com');
});

it('reflects a price change on the next request without caching', function () {
    $dish = feedDish($this->restaurant, ['price' => '32.90']);

    $this->get(route('feed.show', $this->restaurant))->assertSee('R$ 32,90')->assertHeader('Cache-Control', 'no-cache, private');

    $dish->update(['price' => '35.00']);

    $this->get(route('feed.show', $this->restaurant))->assertSee('R$ 35,00');
});

test('a dish with only a photo shows the photo where the video would be', function () {
    $category = Category::factory()->create(['restaurant_id' => $this->restaurant->id]);
    $dish = Dish::factory()->create(['restaurant_id' => $this->restaurant->id, 'category_id' => $category->id, 'name' => 'Só a foto']);
    $dish->photos()->create(['file_path' => 'dishes/so-a-foto.jpg', 'display_order' => 0]);

    $html = $this->get(route('feed.show', $this->restaurant))->assertOk()->assertSee('Só a foto')->getContent();

    expect($html)
        ->toContain('poster="'.Storage::disk('public')->url('dishes/so-a-foto.jpg').'"')
        ->toContain('data-video-src=""');
});

test('the approved video wins over the photo when the dish has both', function () {
    $dish = feedDish($this->restaurant, ['name' => 'Com os dois']);
    $dish->photos()->create(['file_path' => 'dishes/foto.jpg', 'display_order' => 0]);

    $html = $this->get(route('feed.show', $this->restaurant))->getContent();

    expect($html)
        ->toContain('data-video-src="'.MuxUrls::mp4($dish->activeVideo->mux_playback_id).'"')
        ->toContain('poster="'.$dish->activeVideo->cover_path.'"');
});

test('a dish with a pending video and a photo shows the photo, not the video', function () {
    $category = Category::factory()->create(['restaurant_id' => $this->restaurant->id]);
    $dish = Dish::factory()->create(['restaurant_id' => $this->restaurant->id, 'category_id' => $category->id, 'name' => 'Pendente com foto']);
    $dish->photos()->create(['file_path' => 'dishes/p.jpg', 'display_order' => 0]);
    $video = Video::factory()->awaitingApproval()->create(['dish_id' => $dish->id]);
    $dish->update(['active_video_id' => $video->id]);

    $this->get(route('feed.show', $this->restaurant))
        ->assertSee('Pendente com foto')
        ->assertSee('data-video-src=""', false);
});
