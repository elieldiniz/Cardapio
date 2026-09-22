<?php

use App\Filament\Pages\CostsVsRevenue;
use App\Filament\Resources\AiPresets\AiPresetResource;
use App\Filament\Resources\AiProviders\AiProviderResource;
use App\Filament\Resources\Coupons\CouponResource;
use App\Filament\Resources\DishPhotos\DishPhotoResource;
use App\Filament\Resources\Plans\PlanResource;
use App\Filament\Resources\Restaurants\RestaurantResource;
use App\Filament\Resources\VideoAddonPackages\VideoAddonPackageResource;
use App\Filament\Resources\VideoGenerations\VideoGenerationResource;
use App\Filament\Resources\Videos\VideoResource;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\VideoGeneration;

function adminUrls(): array
{
    return [
        RestaurantResource::getUrl(),
        PlanResource::getUrl(),
        PlanResource::getUrl('create'),
        VideoAddonPackageResource::getUrl(),
        CouponResource::getUrl(),
        CouponResource::getUrl('create'),
        VideoGenerationResource::getUrl(),
        AiPresetResource::getUrl(),
        AiProviderResource::getUrl(),
        DishPhotoResource::getUrl(),
        VideoResource::getUrl(),
        CostsVsRevenue::getUrl(),
    ];
}

it('renders every admin screen for a super admin and none for a dono', function () {
    seedReferenceData();
    feedDish(Restaurant::factory()->create());
    VideoGeneration::factory()->errored()->create();

    $this->actingAs(User::factory()->superAdmin()->create());
    foreach (adminUrls() as $url) {
        $this->get($url)->assertOk();
    }

    $this->actingAs(User::factory()->create());
    foreach (adminUrls() as $url) {
        $this->get($url)->assertForbidden();
    }
});
