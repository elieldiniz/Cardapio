<?php

use App\Actions\Cash\OpenCashSession;
use App\Actions\Loyalty\CreditLoyaltyStamp;
use App\Actions\Loyalty\RedeemLoyaltyReward;
use App\Actions\Loyalty\RevertLoyaltyStamp;
use App\Actions\Orders\CancelFinalizedOrder;
use App\Actions\Orders\FinalizeOrder;
use App\Models\Category;
use App\Models\LoyaltyCard;
use App\Models\LoyaltyProgram;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\RestaurantOrderSetting;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * RF-18, RF-08c, RNF-04 — stamps on the loyalty card.
 */
beforeEach(function () {
    seedReferenceData();
    $this->dono = User::factory()->create();
    $this->store = $this->dono->restaurant;
    RestaurantOrderSetting::factory()->create(['restaurant_id' => $this->store->id, 'whatsapp_number' => '5569996041447']);
    $this->dish = feedDish($this->store, ['name' => 'X-Bacon', 'price' => '50.00'], Category::factory()->create(['restaurant_id' => $this->store->id]));
    app(OpenCashSession::class)->handle($this->store, '0');
    $this->program = LoyaltyProgram::create(['restaurant_id' => $this->store->id, 'is_active' => true, 'stamps_required' => 2, 'reward_type' => 'percentual', 'reward_value' => '10.00']);
    $this->placeAndFinalize = function (string $phone = '(69) 99999-0000') {
        $this->postJson(route('feed.orders.store', $this->store), [
            'items' => [['dish_id' => $this->dish->id, 'quantity' => 1]], 'fulfillment' => 'retirada',
            'customer' => ['name' => 'Ana', 'phone' => $phone], 'payment_method' => 'pix',
        ])->assertCreated();
        $order = Order::latest('id')->first();
        $order->update(['status_id' => OrderStatus::idFor('em_rota')]);
        app(FinalizeOrder::class)->handle($order, [['method' => 'pix', 'amount' => '50,00']], $this->dono);

        return $order->fresh();
    };
});

it('gives one stamp per finalized order, and finalizing twice does not duplicate it', function () {
    $order = ($this->placeAndFinalize)();

    app(FinalizeOrder::class)->handle($order, [], $this->dono);
    app(CreditLoyaltyStamp::class)->handle($order);

    expect(LoyaltyCard::sole())->stamps->toBe(1)->completed_at->toBeNull();
});

it('completes the card at the required stamps and stops adding after that', function () {
    ($this->placeAndFinalize)();
    ($this->placeAndFinalize)();
    ($this->placeAndFinalize)();

    $card = LoyaltyCard::sole();

    expect($card)->stamps->toBe(2)->and($card->isRedeemable())->toBeTrue();
});

it('does not stamp when the program is off, below the minimum, or the order has no customer', function () {
    $this->program->update(['min_order' => '80.00']);
    ($this->placeAndFinalize)();
    expect(LoyaltyCard::count())->toBe(0);

    $this->program->update(['min_order' => null, 'is_active' => false]);
    ($this->placeAndFinalize)();
    expect(LoyaltyCard::count())->toBe(0);

    $this->program->update(['is_active' => true]);
    $order = ($this->placeAndFinalize)();
    $other = $order->replicate(['loyalty_card_id', 'loyalty_reverted_at'])->fill(['customer_id' => null, 'code' => 'ZZ-1', 'tracking_token' => 'tok123']);
    $other->save();
    app(CreditLoyaltyStamp::class)->handle($other);
    expect(LoyaltyCard::sole()->stamps)->toBe(1);
});

it('gives the stamp back once when the finalized order is cancelled', function () {
    $first = ($this->placeAndFinalize)();
    $second = ($this->placeAndFinalize)();
    expect(LoyaltyCard::sole()->isRedeemable())->toBeTrue();

    app(CancelFinalizedOrder::class)->handle($second, 'Cliente desistiu', $this->dono);
    app(CancelFinalizedOrder::class)->handle($second->fresh(), 'Cliente desistiu', $this->dono);
    app(RevertLoyaltyStamp::class)->handle($second->fresh());

    $card = LoyaltyCard::sole();

    expect($card)->stamps->toBe(1)->completed_at->toBeNull()->and($first->fresh()->loyalty_reverted_at)->toBeNull();
});

it('redeems the reward once and starts a new card on the next order', function () {
    ($this->placeAndFinalize)();
    ($this->placeAndFinalize)();
    $card = LoyaltyCard::sole();

    app(RedeemLoyaltyReward::class)->handle($card);

    expect(fn () => app(RedeemLoyaltyReward::class)->handle($card))->toThrow(ValidationException::class);

    ($this->placeAndFinalize)();

    expect(LoyaltyCard::count())->toBe(2)->and(LoyaltyCard::whereNull('redeemed_at')->sole()->stamps)->toBe(1);
});

it('refuses to redeem a card that is not complete', function () {
    ($this->placeAndFinalize)();

    expect(fn () => app(RedeemLoyaltyReward::class)->handle(LoyaltyCard::sole()))->toThrow(ValidationException::class);
});

it('keeps a redeemed card untouched when its order is cancelled later', function () {
    ($this->placeAndFinalize)();
    $second = ($this->placeAndFinalize)();
    app(RedeemLoyaltyReward::class)->handle(LoyaltyCard::sole());

    app(CancelFinalizedOrder::class)->handle($second, 'Erro de lançamento', $this->dono);

    expect(LoyaltyCard::sole()->stamps)->toBe(2);
});

it('describes the reward in words', function () {
    expect($this->program->rewardText())->toBe('10% de desconto');

    $this->program->update(['reward_type' => 'valor', 'reward_value' => '15.00']);
    expect($this->program->fresh()->rewardText())->toContain('15,00')->toContain('de desconto');

    $this->program->update(['reward_type' => 'brinde', 'reward_label' => 'Sobremesa grátis']);
    expect($this->program->fresh()->rewardText())->toBe('Sobremesa grátis');
});
