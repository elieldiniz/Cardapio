<?php

use App\Models\Dish;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoGeneration;
use App\Models\VideoStatus;
use App\Notifications\UnreviewedVideosExpiring;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    seedReferenceData();
    $this->mux = fakeVideoPipeline();
    $this->dono = User::factory()->create();
    $this->dish = Dish::factory()->create(['restaurant_id' => $this->dono->restaurant_id]);
});

function roundWith(Dish $dish, int $variations, array $attributes = []): array
{
    $generation = VideoGeneration::factory()->ready()->create(['dish_id' => $dish->id] + $attributes);
    $videos = Video::factory()->awaitingApproval()->count($variations)->create(['dish_id' => $dish->id, 'generation_id' => $generation->id]);

    return [$generation, $videos];
}

function approve(Video $video, $at): void
{
    $video->forceFill(['status_id' => VideoStatus::idFor('aprovado'), 'approved_at' => $at])->save();
    $video->dish->update(['active_video_id' => $video->id]);
}

it('deletes the variations not chosen once a week has passed since the approval', function () {
    [, [$chosen, $other]] = roundWith($this->dish, 2);
    approve($chosen, now()->subDays(8));

    [, [$recentChosen, $recentOther]] = roundWith(Dish::factory()->create(['restaurant_id' => $this->dono->restaurant_id]), 2);
    approve($recentChosen, now()->subDays(2));

    $this->artisan('videos:prune')->assertSuccessful();

    expect(Video::find($other->id))->toBeNull()
        ->and(Video::find($chosen->id))->not->toBeNull()
        ->and(Video::find($recentOther->id))->not->toBeNull()
        ->and($this->mux->deleted)->toBe([$other->mux_asset_id]);
});

it('keeps only the two most recent replaced videos of a dish', function () {
    $active = Video::factory()->approved()->create(['dish_id' => $this->dish->id]);
    $this->dish->update(['active_video_id' => $active->id]);

    $replaced = collect([1, 2, 3, 4])->map(fn (int $daysAgo) => tap(
        Video::factory()->rejected()->create(['dish_id' => $this->dish->id, 'approved_at' => now()->subDays(30)]),
        fn (Video $video) => Video::whereKey($video->id)->update(['updated_at' => now()->subDays($daysAgo)]),
    ));

    $this->artisan('videos:prune')->assertSuccessful();

    expect(Video::whereKey($replaced->pluck('id'))->pluck('id')->sort()->values()->all())
        ->toBe($replaced->take(2)->pluck('id')->sort()->values()->all())
        ->and(Video::find($active->id))->not->toBeNull();
});

it('warns the dono three days before an unreviewed round is removed, then removes it', function () {
    Notification::fake();
    [$round, $videos] = roundWith($this->dish, 2, ['created_at' => now()->subDays(28)]);

    $this->artisan('videos:prune')->assertSuccessful();

    Notification::assertSentTo($this->dono, UnreviewedVideosExpiring::class);
    expect(Video::whereKey($videos->pluck('id'))->count())->toBe(2);

    $this->travel(3)->days();
    $this->artisan('videos:prune')->assertSuccessful();

    expect(Video::whereKey($videos->pluck('id'))->count())->toBe(0);
    Notification::assertSentToTimes($this->dono, UnreviewedVideosExpiring::class, 1);
});

it('never removes the video on the cardápio nor a round the dono approved from', function () {
    [, [$chosen]] = roundWith($this->dish, 1, ['created_at' => now()->subDays(90)]);
    approve($chosen, now()->subDays(60));

    $this->artisan('videos:prune')->assertSuccessful();

    expect(Video::find($chosen->id))->not->toBeNull()
        ->and($this->mux->deleted)->toBe([]);
});
