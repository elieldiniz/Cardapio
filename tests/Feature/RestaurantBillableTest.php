<?php

use App\Models\Restaurant;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;

afterEach(function () {
    ApiRequestor::setHttpClient(null);
});

it('can be created as a stripe customer against a faked stripe client', function () {
    $fakeClient = new class implements ClientInterface
    {
        public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
        {
            $body = json_encode([
                'id' => 'cus_fake123456',
                'object' => 'customer',
                'name' => $params['name'] ?? null,
                'email' => $params['email'] ?? null,
            ]);

            return [$body, 200, []];
        }
    };

    ApiRequestor::setHttpClient($fakeClient);

    $restaurant = Restaurant::factory()->create(['stripe_id' => null]);

    $customer = $restaurant->createAsStripeCustomer();

    expect($customer->id)->toBe('cus_fake123456')
        ->and($restaurant->stripe_id)->toBe('cus_fake123456')
        ->and($restaurant->hasStripeId())->toBeTrue();
});
