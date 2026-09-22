<?php

use App\Actions\Admin\SetRestaurantSuspension;
use App\Filament\Resources\Restaurants\Pages\ListRestaurants;
use App\Models\AdminLog;
use App\Models\Restaurant;
use App\Models\RestaurantStatus;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    seedReferenceData();
    $this->admin = User::factory()->superAdmin()->create();
    $this->restaurant = Restaurant::factory()->create(['slug' => 'fumaca', 'name' => 'Fumaça']);
    $this->dono = User::factory()->create(['restaurant_id' => $this->restaurant->id]);
    feedDish($this->restaurant);
});

test('a suspended restaurants public feed becomes inaccessible', function () {
    app(SetRestaurantSuspension::class)->handle($this->admin, $this->restaurant, true);

    $this->get('/r/fumaca')
        ->assertForbidden()
        ->assertSee('Cardápio indisponível no momento');

    $this->actingAs($this->dono)
        ->get(route('panel.home'))
        ->assertForbidden()
        ->assertSee('Conta suspensa');

    app(SetRestaurantSuspension::class)->handle($this->admin, $this->restaurant->fresh(), false);

    $this->get('/r/fumaca')->assertOk();
    $this->actingAs($this->dono->fresh())->get(route('panel.home'))->assertOk();
});

test('suspending a restaurant writes an audit log entry', function () {
    $this->actingAs($this->admin);

    Livewire::test(ListRestaurants::class)
        ->assertCanSeeTableRecords([$this->restaurant])
        ->callTableAction('suspend', $this->restaurant);

    expect($this->restaurant->fresh()->isSuspended())->toBeTrue();

    $log = AdminLog::with('action')->sole();

    expect($log->action->slug)->toBe('restaurante_suspenso')
        ->and($log->user_id)->toBe($this->admin->id)
        ->and($log->target)->toBe("restaurant:{$this->restaurant->id}")
        ->and($log->created_at)->not->toBeNull();

    Livewire::test(ListRestaurants::class)->callTableAction('reactivate', $this->restaurant->fresh());

    expect(AdminLog::latest('id')->first()->action->slug)->toBe('restaurante_reativado');
});

it('lists restaurants with plan status and usage and filters by status', function () {
    $this->actingAs($this->admin);
    $other = Restaurant::factory()->create();
    app(SetRestaurantSuspension::class)->handle($this->admin, $other, true);

    Livewire::test(ListRestaurants::class)
        ->assertCanSeeTableRecords([$this->restaurant, $other])
        ->assertSee('Grátis')
        ->assertSee('1 / 15')
        ->filterTable('status', RestaurantStatus::idFor('suspenso'))
        ->assertCanSeeTableRecords([$other])
        ->assertCanNotSeeTableRecords([$this->restaurant]);
});
