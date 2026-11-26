<?php

use App\Jobs\FetchCityNeighborhoods;
use App\Models\City;
use App\Models\User;
use App\Services\Address\CityNeighborhoods;
use App\Services\Address\GeoDataImporter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

/**
 * RF-06b — pick a city and tick its neighborhoods instead of typing each one.
 */
beforeEach(function () {
    seedReferenceData();
    $this->dono = User::factory()->create();
});

it('ships the IBGE municipalities and neighborhoods with the migration', function () {
    expect(City::count())->toBeGreaterThan(5500)
        ->and(City::where('state', 'CE')->where('name', 'Fortaleza')->first()->neighborhoods()->count())->toBeGreaterThan(100)
        ->and(City::where('name', 'Fortaleza')->first()->neighborhoods()->where('name', 'Aldeota')->exists())->toBeTrue();
});

it('imports again without duplicating', function () {
    $before = [City::count(), DB::table('neighborhoods')->count()];

    app(GeoDataImporter::class)->import();

    expect([City::count(), DB::table('neighborhoods')->count()])->toBe($before);
});

it('searches cities without caring about accents or case', function () {
    Livewire::actingAs($this->dono)->test('pages::panel.settings')
        ->set('geoState', 'SP')
        ->set('cityQuery', 'sao paul')
        ->assertSee('São Paulo');
});

it('lists the neighborhoods of the city, filters them and adds the ticked ones with the default fee', function () {
    $fortaleza = City::where('state', 'CE')->where('name', 'Fortaleza')->first();

    Livewire::actingAs($this->dono)->test('pages::panel.settings')
        ->set('delivery_mode', 'bairro')
        ->call('selectCity', $fortaleza->id)
        ->assertSet('cityName', 'Fortaleza/CE')
        ->set('bairroFilter', 'aldeota')
        ->assertSee('Aldeota')
        ->set('picked', ['Aldeota', 'Benfica', 'Nao Existe'])
        ->set('defaultFee', '6,50')
        ->call('addPicked')
        ->assertCount('zones', 2)
        ->assertSet('zones.0.name', 'Aldeota')
        ->assertSet('zones.0.fee', '6,50')
        ->assertSet('picked', [])
        ->call('pickAllVisible')
        ->assertSet('picked', [])
        ->call('saveDelivery');

    expect($this->dono->restaurant->deliveryZones()->pluck('fee', 'name')->all())->toBe(['Aldeota' => '6.50', 'Benfica' => '6.50']);
});

it('rejects an invalid default fee', function () {
    $fortaleza = City::where('name', 'Fortaleza')->first();

    Livewire::actingAs($this->dono)->test('pages::panel.settings')
        ->call('selectCity', $fortaleza->id)
        ->set('picked', ['Aldeota'])
        ->set('defaultFee', 'abc')
        ->call('addPicked')
        ->assertHasErrors('defaultFee')
        ->assertCount('zones', 0);
});

it('looks a city IBGE does not cover up on OpenStreetMap once and keeps it', function () {
    Http::fake(['overpass-api.de/*' => Http::response(['elements' => [
        ['tags' => ['name' => 'Bairro Jundiaí']],
        ['tags' => ['name' => 'Anexo Bom Sucesso']],
        ['tags' => ['name' => 'Setor Central']],
        ['tags' => ['name' => 'setor central']],
    ]])]);

    $city = City::where('name', 'Anápolis')->first();
    $service = app(CityNeighborhoods::class);

    expect($service->needsLookup($city))->toBeTrue()
        ->and($service->fetchFromOpenStreetMap($city))->toBeTrue()
        ->and($service->for($city->fresh())->all())->toBe(['Jundiaí', 'Setor Central'])
        ->and($service->needsLookup($city->fresh()))->toBeFalse();
    Http::assertSentCount(1);
});

it('does not mark the city as fetched when OpenStreetMap is down', function () {
    Http::fake(['overpass-api.de/*' => Http::response('x', 502)]);

    $city = City::where('name', 'Anápolis')->first();

    expect(app(CityNeighborhoods::class)->fetchFromOpenStreetMap($city))->toBeFalse()
        ->and($city->fresh()->neighborhoods_fetched_at)->toBeNull()
        ->and(app(CityNeighborhoods::class)->needsLookup($city->fresh()))->toBeTrue();
});

it('fills the checklist of an uncovered city in the background and the screen picks it up', function () {
    Http::fake(['overpass-api.de/*' => Http::response(['elements' => [['tags' => ['name' => 'Jundiaí']], ['tags' => ['name' => 'Setor Central']]]])]);
    $city = City::where('name', 'Anápolis')->first();

    // The queue runs sync in tests: the job finishes right after the screen read the (empty) list,
    // so the next poll picks the neighborhoods up.
    Livewire::actingAs($this->dono)->test('pages::panel.settings')
        ->set('delivery_mode', 'bairro')
        ->call('selectCity', $city->id)
        ->assertSet('cityLoading', true)
        ->call('checkCityBairros')
        ->assertSet('cityBairros', ['Jundiaí', 'Setor Central'])
        ->assertSet('cityLoading', false);
});

it('waits for the background lookup and reports when OpenStreetMap keeps failing', function () {
    Queue::fake();
    $city = City::where('name', 'Anápolis')->first();

    $component = Livewire::actingAs($this->dono)->test('pages::panel.settings')
        ->set('delivery_mode', 'bairro')
        ->call('selectCity', $city->id)
        ->assertSet('cityLoading', true)
        ->assertSee('Buscando os bairros');

    Queue::assertPushed(FetchCityNeighborhoods::class, fn ($job) => $job->cityId === $city->id);

    Cache::put(FetchCityNeighborhoods::failedKey($city->id), true, 60);

    $component->call('checkCityBairros')
        ->assertSet('cityLoading', false)
        ->assertSee('Não consegui buscar os bairros agora')
        ->assertSee('Buscar de novo');
});

it('opens the city of a found CEP so its neighborhoods show up', function () {
    Http::fake(['viacep.com.br/*' => Http::response(['cep' => '60125-040', 'logradouro' => 'Rua X', 'bairro' => 'Aldeota', 'localidade' => 'Fortaleza', 'uf' => 'CE'])]);

    Livewire::actingAs($this->dono)->test('pages::panel.settings')
        ->set('delivery_mode', 'bairro')
        ->set('cep', '60125040')
        ->call('searchCep')
        ->assertSet('cityName', 'Fortaleza/CE');
});
