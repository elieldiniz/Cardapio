<?php

use App\Filament\Resources\DishPhotos\Pages\ListDishPhotos;
use App\Filament\Resources\Videos\Pages\ListVideos;
use App\Models\AdminLog;
use App\Models\DishPhoto;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    seedReferenceData();
    $this->mux = fakeVideoPipeline();
    $this->admin = User::factory()->superAdmin()->create();
    $this->restaurant = Restaurant::factory()->create(['slug' => 'fumaca']);
    $this->actingAs($this->admin);
});

test('removing a dishs active video immediately drops it from the public feed', function () {
    $dish = feedDish($this->restaurant, ['name' => 'Prato impróprio']);
    $video = $dish->activeVideo;

    $this->get('/r/fumaca')->assertSee('Prato impróprio');

    Livewire::test(ListVideos::class)
        ->assertCanSeeTableRecords([$video])
        ->callTableAction('remove', $video, data: ['reason' => 'imagem imprópria']);

    expect($dish->fresh()->active_video_id)->toBeNull()
        ->and($video->fresh()->status->slug)->toBe('rejeitado')
        ->and($this->mux->deleted)->toBe([$video->mux_asset_id]);

    $this->get('/r/fumaca')->assertDontSee('Prato impróprio');
});

test('removing content writes an audit log entry', function () {
    Storage::fake('public');
    $dish = feedDish($this->restaurant);
    $photo = DishPhoto::factory()->create(['dish_id' => $dish->id]);
    Storage::disk('public')->put($photo->file_path, 'image-bytes');

    Livewire::test(ListDishPhotos::class)
        ->callTableAction('remove', $photo, data: ['reason' => 'spam']);

    expect(DishPhoto::find($photo->id))->toBeNull();
    Storage::disk('public')->assertMissing($photo->file_path);

    $log = AdminLog::with('action')->sole();

    expect($log->action->slug)->toBe('conteudo_removido')
        ->and($log->user_id)->toBe($this->admin->id)
        ->and($log->target)->toBe("dish_photo:{$photo->id}")
        ->and($log->data['reason'])->toBe('spam')
        ->and($log->data['restaurant_id'])->toBe($this->restaurant->id);
});

it('filters uploaded content by restaurant', function () {
    $mine = DishPhoto::factory()->create(['dish_id' => feedDish($this->restaurant)->id]);
    $other = DishPhoto::factory()->create();

    Livewire::test(ListDishPhotos::class)
        ->assertCanSeeTableRecords([$mine, $other])
        ->filterTable('restaurant', $this->restaurant->id)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$other]);
});
