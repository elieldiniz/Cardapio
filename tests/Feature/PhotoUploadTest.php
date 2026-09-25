<?php

use App\Models\Category;
use App\Models\Dish;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Symfony\Component\Finder\Finder;

beforeEach(function () {
    seedReferenceData();
    Storage::fake('public');
    $this->dono = User::factory()->create();
    $this->category = Category::factory()->create(['restaurant_id' => $this->dono->restaurant_id]);
    $this->actingAs($this->dono);
});

it('queues photos picked one at a time on the dish form and saves them in order', function () {
    Livewire::test('pages::panel.dish-form')
        ->set('name', 'Costela')
        ->set('category_id', $this->category->id)
        ->set('price', '68,90')
        ->set('photoUpload', fakeImage('frente.png'))
        ->set('photoUpload', fakeImage('lado.png'))
        ->assertCount('newPhotos', 2)
        ->assertSet('photoUpload', null)
        ->call('save')
        ->assertHasNoErrors();

    expect(Dish::firstOrFail()->photos()->orderBy('display_order')->pluck('display_order')->all())->toBe([1, 2]);
});

it('stores each photo right away on the video screen and selects it for the generation', function () {
    $dish = Dish::factory()->create(['restaurant_id' => $this->dono->restaurant_id, 'category_id' => $this->category->id]);

    $component = Livewire::test('pages::panel.dish-video', ['dish' => $dish])
        ->set('photoUpload', fakeImage('frente.png'))
        ->set('photoUpload', fakeImage('lado.png'))
        ->assertHasNoErrors();

    $photos = $dish->photos()->orderBy('display_order')->get();

    expect($photos)->toHaveCount(2)
        ->and($component->get('selectedPhotoIds'))->toBe($photos->pluck('id')->all());
    Storage::disk('public')->assertExists($photos->first()->file_path);
});

it('rejects a file that is not an image', function () {
    $dish = Dish::factory()->create(['restaurant_id' => $this->dono->restaurant_id, 'category_id' => $this->category->id]);

    Livewire::test('pages::panel.dish-video', ['dish' => $dish])
        ->set('photoUpload', UploadedFile::fake()->create('cardapio.pdf', 10, 'application/pdf'))
        ->assertHasErrors('photoUpload');

    expect($dish->photos()->count())->toBe(0);
});

it('never binds a multi-file input straight to Livewire (S3 temporary uploads refuse it)', function () {
    $offenders = collect(Finder::create()->in(resource_path('views'))->name('*.blade.php')->files())
        ->filter(fn ($file) => preg_match('/<input[^>]*wire:model[^>]*multiple|<input[^>]*multiple[^>]*wire:model/s', $file->getContents()))
        ->map(fn ($file) => $file->getRelativePathname())
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});
