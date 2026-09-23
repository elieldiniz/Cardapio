<?php

use App\Filament\Resources\VideoAddonPackages\Pages\CreateVideoAddonPackage;
use App\Filament\Resources\VideoAddonPackages\Pages\EditVideoAddonPackage;
use App\Models\User;
use App\Models\VideoAddonPackage;
use Livewire\Livewire;

it('creates and edits addon packages that the subscription screen then offers', function () {
    seedReferenceData();
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(CreateVideoAddonPackage::class)
        ->fillForm(['name' => 'Pacote 10', 'generations_count' => 10, 'price_cents' => 2900, 'stripe_price_id' => 'price_p10', 'is_active' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    $package = VideoAddonPackage::where('name', 'Pacote 10')->sole();

    Livewire::test(EditVideoAddonPackage::class, ['record' => $package->getRouteKey()])
        ->fillForm(['price_cents' => 2500, 'generations_count' => 12])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($package->fresh())->price_cents->toBe(2500)->generations_count->toBe(12);

    $this->actingAs(User::factory()->create())
        ->get(route('panel.subscription'))
        ->assertSee('Pacote 10')
        ->assertSee('12 gerações · R$ 25,00');
});
