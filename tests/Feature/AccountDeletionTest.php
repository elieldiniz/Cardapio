<?php

use App\Actions\Admin\PurgeRestaurantAccount;
use App\Filament\Resources\Restaurants\Pages\ListRestaurants;
use App\Models\AdminLog;
use App\Models\Category;
use App\Models\DishPhoto;
use App\Models\Plan;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\Video;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Fakes\FakeStripe;

beforeEach(function () {
    seedReferenceData();
    $this->dono = User::factory()->create(['email' => 'ana@fumaca.com']);
    $this->restaurant = $this->dono->restaurant;
    $this->restaurant->update(['name' => 'Fumaça', 'slug' => 'fumaca']);
});

afterEach(fn () => FakeStripe::uninstall());

function deleteAccountAs(User $user, string $confirmation = 'Fumaça', string $password = 'password')
{
    return Livewire::actingAs($user)
        ->test('pages::panel.account')
        ->set('delete_confirmation', $confirmation)
        ->set('delete_password', $password)
        ->call('deleteAccount');
}

it('requires the password and the exact restaurant name', function () {
    deleteAccountAs($this->dono, 'fumaca')->assertHasErrors('delete_confirmation');
    deleteAccountAs($this->dono, 'Fumaça', 'errada')->assertHasErrors('delete_password');

    expect($this->restaurant->fresh()->trashed())->toBeFalse();
});

it('sends the account to the trash, takes the cardápio down and blocks login', function () {
    feedDish($this->restaurant, ['name' => 'Burger']);

    deleteAccountAs($this->dono)->assertHasNoErrors()->assertRedirect(url('/'));

    expect(Restaurant::withTrashed()->find($this->restaurant->id)->trashed())->toBeTrue()
        ->and(User::withTrashed()->find($this->dono->id)->trashed())->toBeTrue()
        ->and(Restaurant::find($this->restaurant->id))->toBeNull();

    $this->get('/r/fumaca')->assertNotFound();

    auth()->logout();
    Livewire::test('pages::auth.login')
        ->set('email', 'ana@fumaca.com')
        ->set('password', 'password')
        ->call('login')
        ->assertHasErrors('email');
});

it('cancels an active paid subscription when the dono deletes the account', function () {
    $stripe = FakeStripe::install();
    $this->restaurant->update(['stripe_id' => 'cus_123', 'plan_id' => Plan::where('name', 'Pro')->value('id')]);
    $this->restaurant->subscriptions()->create(['type' => 'default', 'stripe_id' => 'sub_1', 'stripe_status' => 'active', 'stripe_price' => 'price_pro']);

    deleteAccountAs($this->dono->fresh())->assertHasNoErrors();

    expect(collect($stripe->requests)->where('method', 'DELETE')->pluck('path')->all())->toContain('/v1/subscriptions/sub_1')
        ->and(Restaurant::withTrashed()->find($this->restaurant->id)->plan->name)->toBe('Grátis');
});

it('lists deleted accounts for the super admin who can restore them', function () {
    deleteAccountAs($this->dono);
    $admin = User::factory()->superAdmin()->create();
    $trashed = Restaurant::withTrashed()->find($this->restaurant->id);

    Livewire::actingAs($admin)
        ->test(ListRestaurants::class)
        ->assertCanNotSeeTableRecords([$trashed])
        ->set('activeTab', 'excluidos')
        ->assertCanSeeTableRecords([$trashed])
        ->callTableAction('restore', $trashed);

    expect($this->restaurant->fresh()->trashed())->toBeFalse()
        ->and(User::find($this->dono->id))->not->toBeNull()
        ->and(AdminLog::with('action')->latest('id')->first()->action->slug)->toBe('conta_restaurada');

    $this->get('/r/fumaca')->assertOk();
});

it('lets the super admin purge a deleted account for good', function () {
    Storage::fake('public');
    $mux = fakeVideoPipeline();
    $category = Category::factory()->create(['restaurant_id' => $this->restaurant->id]);
    $dish = feedDish($this->restaurant, [], $category);
    $photo = DishPhoto::factory()->create(['dish_id' => $dish->id]);
    Storage::disk('public')->put($photo->file_path, 'x');
    $assetId = $dish->activeVideo->mux_asset_id;

    deleteAccountAs($this->dono);
    $admin = User::factory()->superAdmin()->create();
    $trashed = Restaurant::withTrashed()->find($this->restaurant->id);

    Livewire::actingAs($admin)
        ->test(ListRestaurants::class)
        ->set('activeTab', 'excluidos')
        ->callTableAction('purge', $trashed);

    expect(Restaurant::withTrashed()->find($this->restaurant->id))->toBeNull()
        ->and(User::withTrashed()->find($this->dono->id))->toBeNull()
        ->and(Video::count())->toBe(0)
        ->and($mux->deleted)->toBe([$assetId])
        ->and(AdminLog::with('action')->latest('id')->first())
        ->action->slug->toBe('conta_excluida_definitivamente');

    Storage::disk('public')->assertMissing($photo->file_path);
});

it('never purges an account that was not deleted by its dono', function () {
    $admin = User::factory()->superAdmin()->create();

    expect(fn () => app(PurgeRestaurantAccount::class)->handle($admin, $this->restaurant))
        ->toThrow(InvalidArgumentException::class);

    Livewire::actingAs($admin)
        ->test(ListRestaurants::class)
        ->assertTableActionHidden('purge', $this->restaurant)
        ->assertTableActionHidden('restore', $this->restaurant);
});
