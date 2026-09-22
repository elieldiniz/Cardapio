<?php

use App\Actions\Admin\SetRestaurantSuspension;
use App\Filament\Resources\Restaurants\Pages\ListRestaurants;
use App\Models\AdminLog;
use App\Models\Restaurant;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    seedReferenceData();
    $this->admin = User::factory()->superAdmin()->create();
    $this->restaurant = Restaurant::factory()->create(['name' => 'Fumaça']);
    $this->dono = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'name' => 'Ana']);
});

test('impersonating a restaurant grants access to its panel and logs the action', function () {
    $this->actingAs($this->admin);

    Livewire::test(ListRestaurants::class)
        ->callTableAction('impersonate', $this->restaurant)
        ->assertRedirect(route('panel.home'));

    $this->assertAuthenticatedAs($this->dono);

    $this->get(route('panel.home'))
        ->assertOk()
        ->assertSee('Modo suporte: você está vendo o painel como Ana (Fumaça)')
        ->assertSee('Voltar ao admin');

    $log = AdminLog::with('action')->sole();
    expect($log->action->slug)->toBe('restaurante_impersonado')
        ->and($log->user_id)->toBe($this->admin->id)
        ->and($log->target)->toBe("restaurant:{$this->restaurant->id}");
});

test('ending impersonation returns to the super admin session', function () {
    $this->actingAs($this->admin);
    Livewire::test(ListRestaurants::class)->callTableAction('impersonate', $this->restaurant);

    $this->post(route('impersonation.stop'))->assertRedirect('/admin');

    $this->assertAuthenticatedAs($this->admin);
    $this->get('/admin')->assertOk();
});

it('lets a super admin support a suspended restaurant while impersonating', function () {
    app(SetRestaurantSuspension::class)->handle($this->admin, $this->restaurant, true);
    $this->actingAs($this->admin);
    Livewire::test(ListRestaurants::class)->callTableAction('impersonate', $this->restaurant->fresh());

    $this->get(route('panel.home'))->assertOk();
});

it('does not let a dono end an impersonation that never started', function () {
    $this->actingAs($this->dono)->post(route('impersonation.stop'))->assertRedirect(route('login'));

    $this->assertGuest();
});
