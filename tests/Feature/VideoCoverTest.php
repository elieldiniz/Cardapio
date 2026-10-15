<?php

use App\Actions\Ai\RequestVideoGeneration;
use App\Actions\Videos\StartOwnVideoUpload;
use App\Models\Category;
use App\Models\Dish;
use App\Models\DishPhoto;
use App\Models\User;
use App\Models\Video;
use App\Services\Feed\FeedBuilder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    seedReferenceData();
    Storage::fake('public');
});

it('makes the photo a generated video came from its cover, as a copy that outlives the original', function () {
    fakeVideoPipeline();
    [, $dish] = ownerWithDish(photos: 1, addon: 5);
    $photo = $dish->photos->first();
    Storage::disk('public')->put($photo->file_path, fakeImage()->getContent());

    app(RequestVideoGeneration::class)->handle($dish, [$photo->id]);

    $video = Video::sole();

    expect($video->cover_photo_path)->toStartWith('video-covers/'.$dish->restaurant_id.'/')
        ->and($video->cover_photo_path)->not->toBe($photo->file_path)
        ->and($video->coverUrl())->toBe(Storage::disk('public')->url($video->cover_photo_path));

    Storage::disk('public')->delete($photo->file_path);
    Storage::disk('public')->assertExists($video->cover_photo_path);
});

it('has no cover photo when the source file is missing, and falls back to the Mux frame', function () {
    fakeVideoPipeline();
    [, $dish] = ownerWithDish(photos: 1, addon: 5);

    app(RequestVideoGeneration::class)->handle($dish, $dish->photos->pluck('id')->all());

    $video = Video::sole();
    $video->update(['cover_path' => 'https://image.mux.test/abc/thumbnail.webp?time=0']);

    expect($video->cover_photo_path)->toBeNull()
        ->and($video->fresh()->coverUrl())->toBe('https://image.mux.test/abc/thumbnail.webp?time=0');
});

it('shows the chosen cover on the grid and as the poster, then an older dish photo, then the Mux frame', function () {
    $dono = User::factory()->create();
    $dish = feedDish($dono->restaurant);
    $video = $dish->activeVideo;
    $video->update(['mux_playback_id' => 'pb123', 'cover_path' => null, 'cover_photo_path' => null]);
    $builder = app(FeedBuilder::class);

    // 1. Nothing chosen: the Mux frame.
    $dish = $dish->fresh(['activeVideo', 'photos', 'badges', 'variants']);
    expect($builder->present($dish)['thumb_url'])->toContain('pb123');

    // 2. An older dish photo (legacy) wins over the frame for the grid.
    DishPhoto::factory()->create(['dish_id' => $dish->id, 'file_path' => 'dish-photos/legacy.jpg', 'display_order' => 0]);
    $dish = $dish->fresh(['activeVideo', 'photos', 'badges', 'variants']);
    expect($builder->present($dish)['thumb_url'])->toBe(Storage::disk('public')->url('dish-photos/legacy.jpg'));

    // 3. The cover chosen for the video wins over everything, for the grid and the poster.
    $video->update(['cover_photo_path' => 'video-covers/1/chosen.jpg']);
    $dish = $dish->fresh(['activeVideo', 'photos', 'badges', 'variants']);
    $presented = $builder->present($dish);

    expect($presented['thumb_url'])->toBe(Storage::disk('public')->url('video-covers/1/chosen.jpg'))
        ->and($presented['cover_url'])->toBe(Storage::disk('public')->url('video-covers/1/chosen.jpg'));
});

it('lets the dono send a cover with an own video and attaches it to that upload only', function () {
    $dono = User::factory()->create();
    $dish = Dish::factory()->create(['restaurant_id' => $dono->restaurant_id, 'category_id' => Category::factory()->create(['restaurant_id' => $dono->restaurant_id])->id]);
    fakeVideoPipeline();

    $component = Livewire::actingAs($dono)->test('pages::panel.dish-video', ['dish' => $dish])
        ->assertSeeHtml('data-own-cover')
        ->assertSee('Capa do vídeo (opcional)')
        ->set('coverUpload', fakeImage('capa.png'))
        ->assertHasNoErrors()
        ->assertSee('Será a capa do próximo vídeo');

    $cover = $component->get('pendingCover');
    Storage::disk('public')->assertExists($cover);

    $result = $component->instance()->startUpload(8.0, 720, 1280, app(StartOwnVideoUpload::class));
    expect($result['ok'])->toBeTrue();

    expect(Video::sole()->cover_photo_path)->toBe($cover);

    // The cover belonged to that video: the next one starts without it.
    $component->assertSet('pendingCover', null);
    $second = $component->instance()->startUpload(8.0, 720, 1280, app(StartOwnVideoUpload::class));
    expect($second['ok'])->toBeTrue()
        ->and(Video::query()->latest('id')->first()->cover_photo_path)->toBeNull();
});

it('replaces and removes the pending cover, deleting the discarded file', function () {
    $dono = User::factory()->create();
    $dish = Dish::factory()->create(['restaurant_id' => $dono->restaurant_id, 'category_id' => Category::factory()->create(['restaurant_id' => $dono->restaurant_id])->id]);

    $component = Livewire::actingAs($dono)->test('pages::panel.dish-video', ['dish' => $dish])->set('coverUpload', fakeImage('a.png'));
    $first = $component->get('pendingCover');

    $component->set('coverUpload', fakeImage('b.png'));
    $second = $component->get('pendingCover');

    expect($second)->not->toBe($first);
    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($second);

    $component->call('removeCover')->assertSet('pendingCover', null);
    Storage::disk('public')->assertMissing($second);
});

it('rejects a cover that is not an image and never trusts a path sent by the browser', function () {
    $dono = User::factory()->create();
    $dish = Dish::factory()->create(['restaurant_id' => $dono->restaurant_id, 'category_id' => Category::factory()->create(['restaurant_id' => $dono->restaurant_id])->id]);

    Livewire::actingAs($dono)->test('pages::panel.dish-video', ['dish' => $dish])
        ->set('coverUpload', UploadedFile::fake()->create('capa.pdf', 10, 'application/pdf'))
        ->assertHasErrors('coverUpload')
        ->assertSet('pendingCover', null);

    // pendingCover is locked: a forged value is refused by Livewire.
    expect(fn () => Livewire::actingAs($dono)->test('pages::panel.dish-video', ['dish' => $dish])->set('pendingCover', 'dish-photos/1/other-restaurant.jpg'))
        ->toThrow(Exception::class);
});

it('uses the cover as the poster of the video player in the panel', function () {
    $dono = User::factory()->create();
    $dish = feedDish($dono->restaurant);
    $dish->activeVideo->update(['mux_playback_id' => 'pb9', 'cover_photo_path' => 'video-covers/1/p.jpg']);

    Livewire::actingAs($dono)->test('pages::panel.dish-video', ['dish' => $dish])
        ->assertSeeHtml('poster="'.Storage::disk('public')->url('video-covers/1/p.jpg').'"');
});
