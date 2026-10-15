<?php

use App\Actions\Ai\RequestVideoGeneration;
use App\Exceptions\InsufficientGenerationBalanceException;
use App\Jobs\GenerateDishVideo;
use App\Models\VideoGeneration;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    seedReferenceData();
    fakeVideoPipeline();
    Queue::fake();
});

test('a request is blocked when the balance cannot cover it', function () {
    [$dono, $dish] = ownerWithDish(photos: 1, monthly: 0, addon: 0);

    expect(fn () => app(RequestVideoGeneration::class)->handle($dish, $dish->photos->pluck('id')->all()))
        ->toThrow(InsufficientGenerationBalanceException::class);

    $this->actingAs($dono);

    videoScreenWithPhoto($dish)
        ->assertSeeHtml('data-balance-blocked')
        ->call('generate')
        ->assertHasErrors('balance');

    expect(VideoGeneration::count())->toBe(0);
    Queue::assertNothingPushed();
});

test('the panel shows the exact balance that will be consumed before confirming', function () {
    [$dono, $dish] = ownerWithDish(photos: 1, monthly: 4, addon: 5);

    $this->actingAs($dono);

    Livewire::test('pages::panel.dish-video', ['dish' => $dish])
        ->assertSeeHtml('<strong data-consumption>1 geração</strong>')
        ->assertSeeHtml('Saldo disponível: <strong class="text-ink">9</strong>')
        ->assertDontSeeHtml('data-balance-blocked')
        ->assertDontSee('variações');
});

it('queues a generation from the single chosen photo and the preset provider snapshot', function () {
    [$dono, $dish] = ownerWithDish(photos: 4, addon: 5);
    // A dish can hold several photos, but the video is made from exactly one: the one chosen.
    $photoIds = [$dish->photos->sortBy('display_order')->first()->id];

    $this->actingAs($dono);

    Livewire::test('pages::panel.dish-video', ['dish' => $dish])
        ->assertSet('selectedPhotoIds', [])
        ->set('selectedPhotoIds', $photoIds)
        ->call('generate')
        ->assertHasNoErrors();

    $generation = VideoGeneration::with(['photos', 'status', 'preset'])->sole();

    expect($generation->status->slug)->toBe('fila')
        ->and($generation->variations_requested)->toBe(1)
        ->and($generation->provider_id)->toBe($generation->preset->provider_id)
        ->and($generation->photos->sortBy('angle_order')->pluck('dish_photo_id')->all())->toBe($photoIds)
        ->and($generation->photos->pluck('angle_order')->all())->toBe([1]);

    Queue::assertPushed(GenerateDishVideo::class, fn (GenerateDishVideo $job) => $job->generationId === $generation->id);
});

it('holds back balance already reserved by queued generations', function () {
    [, $dish] = ownerWithDish(photos: 1, addon: 5);
    $photos = $dish->photos->pluck('id')->all();

    app(RequestVideoGeneration::class)->handle($dish, $photos, 3);

    expect(fn () => app(RequestVideoGeneration::class)->handle($dish, $photos, 3))
        ->toThrow(InsufficientGenerationBalanceException::class);
});

it('accepts exactly 1 photo and 1 to 3 videos only', function (int $photoCount, int $variations) {
    [, $dish] = ownerWithDish(photos: 5, addon: 10);

    app(RequestVideoGeneration::class)->handle($dish, $dish->photos->take($photoCount)->pluck('id')->all(), $variations);
})->with([
    'no photo' => [0, 2],
    'two photos' => [2, 2],
    'five photos' => [5, 2],
    'zero videos' => [1, 0],
    'four videos' => [1, 4],
])->throws(InvalidArgumentException::class);

it('always asks for a single video and cannot be tampered into asking for more', function () {
    [$dono, $dish] = ownerWithDish(photos: 1, addon: 10);

    $this->actingAs($dono);

    // The page has no "variations" field at all: forging one is rejected by Livewire.
    expect(fn () => Livewire::test('pages::panel.dish-video', ['dish' => $dish])->set('variations', 3))
        ->toThrow(Exception::class);

    videoScreenWithPhoto($dish)->call('generate')->assertHasNoErrors();

    expect(VideoGeneration::sole()->variations_requested)->toBe(1)
        ->and(app(RequestVideoGeneration::class)->handle($dish, $dish->photos->pluck('id')->all())->variations_requested)->toBe(1);
});

it('opens the photo slot empty even when the dish already has photos, and clears it when generation starts', function () {
    [$dono, $dish] = ownerWithDish(photos: 3, addon: 5);
    $ids = $dish->photos->sortBy('display_order')->pluck('id')->all();

    $this->actingAs($dono);

    // Nothing is chosen on opening: the dono has to upload a photo for the video.
    Livewire::test('pages::panel.dish-video', ['dish' => $dish])
        ->assertSet('selectedPhotoIds', [])
        ->assertSee('Enviar foto')
        ->assertDontSee('Trocar foto')
        ->call('generate')
        ->assertHasErrors('selectedPhotoIds');

    expect(VideoGeneration::count())->toBe(0);

    // Once a photo is uploaded the slot shows it; starting the generation clears it again.
    $component = Livewire::test('pages::panel.dish-video', ['dish' => $dish])
        ->set('photoUpload', fakeImage('prato.png'))
        ->assertSee('Trocar foto')
        ->call('generate')
        ->assertHasNoErrors()
        ->assertSet('selectedPhotoIds', []);

    $uploaded = $dish->photos()->whereNotIn('id', $ids)->sole();

    expect(VideoGeneration::sole()->photos->pluck('dish_photo_id')->all())->toBe([$uploaded->id]);
});
