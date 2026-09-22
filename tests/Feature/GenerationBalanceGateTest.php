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

test('a request is blocked when the balance cannot cover the requested variations', function () {
    [$dono, $dish] = ownerWithDish(photos: 1, monthly: 1, addon: 1);

    expect(fn () => app(RequestVideoGeneration::class)->handle($dish, $dish->photos->pluck('id')->all(), 3))
        ->toThrow(InsufficientGenerationBalanceException::class);

    $this->actingAs($dono);

    Livewire::test('pages::panel.dish-video', ['dish' => $dish])
        ->set('variations', 3)
        ->assertSeeHtml('data-balance-blocked')
        ->call('generate')
        ->assertHasErrors('variations');

    expect(VideoGeneration::count())->toBe(0);
    Queue::assertNothingPushed();
});

test('the panel shows the exact balance that will be consumed before confirming', function () {
    [$dono, $dish] = ownerWithDish(photos: 1, monthly: 4, addon: 5);

    $this->actingAs($dono);

    Livewire::test('pages::panel.dish-video', ['dish' => $dish])
        ->set('variations', 3)
        ->assertSeeHtml('<strong data-consumption>3 gerações</strong>')
        ->assertSeeHtml('Saldo disponível: <strong class="text-ink">9</strong>')
        ->assertDontSeeHtml('data-balance-blocked')
        ->set('variations', 2)
        ->assertSeeHtml('<strong data-consumption>2 gerações</strong>');
});

it('queues a generation with one photo row per angle and the preset provider snapshot', function () {
    [$dono, $dish] = ownerWithDish(photos: 4, addon: 5);
    $photoIds = $dish->photos->sortBy('display_order')->pluck('id')->all();

    $this->actingAs($dono);

    Livewire::test('pages::panel.dish-video', ['dish' => $dish])
        ->assertSet('selectedPhotoIds', $photoIds)
        ->set('variations', 2)
        ->call('generate')
        ->assertHasNoErrors();

    $generation = VideoGeneration::with(['photos', 'status', 'preset'])->sole();

    expect($generation->status->slug)->toBe('fila')
        ->and($generation->variations_requested)->toBe(2)
        ->and($generation->provider_id)->toBe($generation->preset->provider_id)
        ->and($generation->photos->sortBy('angle_order')->pluck('dish_photo_id')->all())->toBe($photoIds)
        ->and($generation->photos->pluck('angle_order')->sort()->values()->all())->toBe([1, 2, 3, 4]);

    Queue::assertPushed(GenerateDishVideo::class, fn (GenerateDishVideo $job) => $job->generationId === $generation->id);
});

it('holds back balance already reserved by queued generations', function () {
    [, $dish] = ownerWithDish(photos: 1, addon: 5);
    $photos = $dish->photos->pluck('id')->all();

    app(RequestVideoGeneration::class)->handle($dish, $photos, 3);

    expect(fn () => app(RequestVideoGeneration::class)->handle($dish, $photos, 3))
        ->toThrow(InsufficientGenerationBalanceException::class);
});

it('accepts 1 to 4 photos and 2 to 3 variations only', function (int $photoCount, int $variations) {
    [, $dish] = ownerWithDish(photos: 5, addon: 10);

    app(RequestVideoGeneration::class)->handle($dish, $dish->photos->take($photoCount)->pluck('id')->all(), $variations);
})->with([
    'no photo' => [0, 2],
    'five photos' => [5, 2],
    'one variation' => [1, 1],
    'four variations' => [1, 4],
])->throws(InvalidArgumentException::class);
