<?php

use App\Actions\Videos\ApproveVideo;
use App\Models\Dish;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoGeneration;
use Livewire\Livewire;

beforeEach(function () {
    seedReferenceData();
    $this->dono = User::factory()->create();
    $this->dish = Dish::factory()->create(['restaurant_id' => $this->dono->restaurant_id]);
    $this->actingAs($this->dono);
});

test('approving a variation sets it as the dishs only active video', function () {
    $generation = VideoGeneration::factory()->ready()->create(['dish_id' => $this->dish->id]);
    [$first, $second] = Video::factory()->awaitingApproval()->count(2)->create(['dish_id' => $this->dish->id, 'generation_id' => $generation->id]);

    Livewire::test('pages::panel.dish-video', ['dish' => $this->dish])
        ->assertSee('Aguardando aprovação')
        ->call('approve', $second->id);

    $this->dish->refresh();

    expect($this->dish->active_video_id)->toBe($second->id)
        ->and($second->fresh()->status->slug)->toBe('aprovado')
        ->and($first->fresh()->status->slug)->toBe('aguardando_aprovacao');
});

test('a dish never ends up with two active videos', function () {
    $old = Video::factory()->approved()->create(['dish_id' => $this->dish->id]);
    $this->dish->update(['active_video_id' => $old->id]);
    $new = Video::factory()->awaitingApproval()->create(['dish_id' => $this->dish->id]);

    app(ApproveVideo::class)->handle($new);

    expect($this->dish->fresh()->active_video_id)->toBe($new->id)
        ->and(Video::where('dish_id', $this->dish->id)->whereHas('status', fn ($s) => $s->where('slug', 'aprovado'))->pluck('id')->all())->toBe([$new->id])
        ->and($old->fresh()->status->slug)->toBe('rejeitado');
});

it('refuses to approve a video that is still processing', function () {
    $processing = Video::factory()->create(['dish_id' => $this->dish->id]);

    Livewire::test('pages::panel.dish-video', ['dish' => $this->dish])->call('approve', $processing->id);

    expect($this->dish->fresh()->active_video_id)->toBeNull();
});

it('labels variations from earlier rounds as discarded after regenerating', function () {
    $older = VideoGeneration::factory()->ready()->create(['dish_id' => $this->dish->id]);
    Video::factory()->awaitingApproval()->create(['dish_id' => $this->dish->id, 'generation_id' => $older->id]);
    $latest = VideoGeneration::factory()->ready()->create(['dish_id' => $this->dish->id]);
    Video::factory()->awaitingApproval()->create(['dish_id' => $this->dish->id, 'generation_id' => $latest->id]);

    Livewire::test('pages::panel.dish-video', ['dish' => $this->dish])
        ->assertSeeInOrder(['Rodada mais recente', 'Aguardando aprovação', 'Rodada anterior', 'Descartada'])
        ->assertSee('Gerar de novo');
});

it('returns 404 for another restaurants dish', function () {
    $this->get(route('panel.dishes.video', Dish::factory()->create()))->assertNotFound();
});
