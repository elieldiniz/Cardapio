<?php

use App\Filament\Resources\Coupons\Pages\CreateCoupon;
use App\Filament\Resources\Coupons\Pages\EditCoupon;
use App\Models\AdminLog;
use App\Models\Coupon;
use App\Models\DiscountType;
use App\Models\User;
use Livewire\Livewire;
use Tests\Fakes\FakeStripe;

beforeEach(function () {
    seedReferenceData();
    $this->stripe = FakeStripe::install();
    $this->actingAs(User::factory()->superAdmin()->create());
});

afterEach(fn () => FakeStripe::uninstall());

test('creating a coupon syncs a matching stripe coupon', function () {
    Livewire::test(CreateCoupon::class)
        ->fillForm([
            'code' => 'bemvindo20',
            'discount_type_id' => DiscountType::idFor('percentual'),
            'discount_value' => 20,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $coupon = Coupon::sole();
    $stripeCoupon = $this->stripe->requestsTo('/v1/coupons')[0]['params'];
    $promotion = $this->stripe->requestsTo('/v1/promotion_codes')[0]['params'];

    expect($coupon->code)->toBe('BEMVINDO20')
        ->and($coupon->stripe_coupon_id)->toBe('coupon_fake_1')
        ->and((float) $stripeCoupon['percent_off'])->toBe(20.0)
        ->and($stripeCoupon['name'])->toBe('BEMVINDO20')
        ->and($promotion['code'])->toBe('BEMVINDO20')
        ->and($promotion['promotion'])->toBe(['type' => 'coupon', 'coupon' => 'coupon_fake_1'])
        ->and(AdminLog::with('action')->sole()->action->slug)->toBe('cupom_criado');
});

it('syncs a fixed value coupon in cents and brl', function () {
    Coupon::create(['code' => 'MENOS15', 'discount_type_id' => DiscountType::idFor('valor_fixo'), 'discount_value' => 15.5, 'is_active' => true]);

    $params = $this->stripe->requestsTo('/v1/coupons')[0]['params'];

    expect($params['amount_off'])->toBe(1550)
        ->and($params['currency'])->toBe(config('cashier.currency'));
});

it('replaces the stripe coupon when the discount changes and deletes it when deactivated', function () {
    $coupon = Coupon::create(['code' => 'PROMO', 'discount_type_id' => DiscountType::idFor('percentual'), 'discount_value' => 10, 'is_active' => true]);

    Livewire::test(EditCoupon::class, ['record' => $coupon->getRouteKey()])
        ->fillForm(['discount_value' => 25])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($coupon->fresh()->stripe_coupon_id)->toBe('coupon_fake_2')
        ->and(collect($this->stripe->requests)->where('method', 'DELETE')->pluck('path')->all())->toBe(['/v1/coupons/coupon_fake_1']);

    $coupon->fresh()->update(['is_active' => false]);

    expect($coupon->fresh()->stripe_coupon_id)->toBeNull()
        ->and(collect($this->stripe->requests)->where('method', 'DELETE')->pluck('path')->last())->toBe('/v1/coupons/coupon_fake_2');
});
