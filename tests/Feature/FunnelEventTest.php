<?php

use App\Models\Category;
use App\Models\FunnelEvent;
use App\Models\User;

/**
 * RF-21, RNF-05, CT-04 — the funnel endpoint.
 */
beforeEach(function () {
    seedReferenceData();
    $this->dono = User::factory()->create();
    $this->store = $this->dono->restaurant;
    $this->dish = feedDish($this->store, ['name' => 'X-Bacon', 'price' => '50.00'], Category::factory()->create(['restaurant_id' => $this->store->id]));
    $this->send = fn (array $body) => $this->postJson(route('feed.events', $this->store), array_merge(['session_token' => 'sessao-abc-123'], $body));
});

it('answers 204 and records the step', function () {
    ($this->send)(['event' => 'visit'])->assertNoContent();

    expect(FunnelEvent::sole())->event->toBe('visit')->dish_id->toBe(0);
});

it('does not inflate the numbers when a step repeats in the same session and day', function () {
    foreach (range(1, 4) as $_) {
        ($this->send)(['event' => 'visit'])->assertNoContent();
        ($this->send)(['event' => 'checkout'])->assertNoContent();
        ($this->send)(['event' => 'add', 'dish_id' => $this->dish->id])->assertNoContent();
    }

    expect(FunnelEvent::count())->toBe(3);

    $this->postJson(route('feed.events', $this->store), ['session_token' => 'outra-sessao-9', 'event' => 'visit'])->assertNoContent();

    expect(FunnelEvent::where('event', 'visit')->count())->toBe(2);
});

it('counts an "add" per product and ignores a product of another store', function () {
    $other = feedDish(User::factory()->create()->restaurant, ['name' => 'Alheio', 'price' => '9.00']);

    ($this->send)(['event' => 'add', 'dish_id' => $this->dish->id])->assertNoContent();
    ($this->send)(['event' => 'add', 'dish_id' => $other->id])->assertNoContent();

    expect(FunnelEvent::orderBy('id')->pluck('dish_id')->map(fn ($id) => (int) $id)->all())->toBe([$this->dish->id, 0]);
});

it('rejects an unknown event or a missing session and accepts a text/plain beacon', function () {
    ($this->send)(['event' => 'hack'])->assertUnprocessable();
    $this->postJson(route('feed.events', $this->store), ['event' => 'visit'])->assertUnprocessable();

    $this->call('POST', route('feed.events', $this->store), [], [], [], ['CONTENT_TYPE' => 'text/plain'], json_encode(['session_token' => 'beacon-sessao-1', 'event' => 'visit']))->assertNoContent();

    expect(FunnelEvent::count())->toBe(1);
});

it('is throttled', function () {
    foreach (range(1, 120) as $i) {
        $this->postJson(route('feed.events', $this->store), ['session_token' => "sessao-numero-{$i}", 'event' => 'visit'])->assertNoContent();
    }

    $this->postJson(route('feed.events', $this->store), ['session_token' => 'sessao-extra-1', 'event' => 'visit'])->assertStatus(429);
});
