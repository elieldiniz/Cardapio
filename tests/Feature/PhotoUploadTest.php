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

it('no longer takes photos on the dish form: the photo comes with the video', function () {
    $dish = Dish::factory()->create(['restaurant_id' => $this->dono->restaurant_id, 'category_id' => $this->category->id]);

    Livewire::test('pages::panel.dish-form', ['dish' => $dish])
        ->assertDontSee('Adicionar')
        ->assertDontSeeHtml('type="file"')
        ->assertSee('Foto e vídeo do prato')
        ->assertSee('vira a capa dele no cardápio');

    // A new dish has nothing to link to yet.
    Livewire::test('pages::panel.dish-form')->assertDontSee('Foto e vídeo do prato');
});

it('stores each photo right away on the video screen and selects the latest one for the generation', function () {
    $dish = Dish::factory()->create(['restaurant_id' => $this->dono->restaurant_id, 'category_id' => $this->category->id]);

    $component = Livewire::test('pages::panel.dish-video', ['dish' => $dish])
        ->set('photoUpload', fakeImage('frente.png'))
        ->set('photoUpload', fakeImage('lado.png'))
        ->assertHasNoErrors();

    $photos = $dish->photos()->orderBy('display_order')->get();

    expect($photos)->toHaveCount(2)
        ->and($component->get('selectedPhotoIds'))->toBe([$photos->last()->id]);
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
