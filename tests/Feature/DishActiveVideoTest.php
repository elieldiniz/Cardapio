<?php

use App\Models\Dish;
use App\Models\Video;

it('a dish has at most one active video at a time', function () {
    $dish = Dish::factory()->create();
    $videoA = Video::factory()->approved()->create(['dish_id' => $dish->id]);
    $videoB = Video::factory()->approved()->create(['dish_id' => $dish->id]);

    $dish->update(['active_video_id' => $videoA->id]);

    expect($dish->fresh()->activeVideo->is($videoA))->toBeTrue();

    $dish->update(['active_video_id' => $videoB->id]);

    expect($dish->fresh()->activeVideo->is($videoB))->toBeTrue();
});
