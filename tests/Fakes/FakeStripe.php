<?php

namespace Tests\Fakes;

use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;

/**
 * Stands in for the Stripe HTTP API: records every request and answers the
 * endpoints the app uses with minimal, realistic objects.
 */
class FakeStripe implements ClientInterface
{
    /** @var array<int, array{method: string, path: string, params: array<string, mixed>}> */
    public array $requests = [];

    public static function install(): self
    {
        $fake = new self;
        ApiRequestor::setHttpClient($fake);

        return $fake;
    }

    public static function uninstall(): void
    {
        ApiRequestor::setHttpClient(null);
    }

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $path = parse_url($absUrl, PHP_URL_PATH);
        $this->requests[] = ['method' => strtoupper($method), 'path' => $path, 'params' => $params ?? []];
        $count = count($this->requestsTo('/v1/checkout/sessions'));

        $body = match (true) {
            str_starts_with($path, '/v1/customers') => ['id' => 'cus_fake', 'object' => 'customer', 'email' => $params['email'] ?? null, 'name' => $params['name'] ?? null],
            $path === '/v1/checkout/sessions' => ['id' => "cs_fake_{$count}", 'object' => 'checkout.session', 'url' => "https://checkout.stripe.test/cs_fake_{$count}", 'mode' => $params['mode'] ?? null],
            $path === '/v1/billing_portal/sessions' => ['id' => 'bps_fake', 'object' => 'billing_portal.session', 'url' => 'https://billing.stripe.test/session'],
            str_starts_with($path, '/v1/coupons/') && strtoupper($method) === 'DELETE' => ['id' => basename($path), 'object' => 'coupon', 'deleted' => true],
            $path === '/v1/coupons' => array_merge(['object' => 'coupon', 'valid' => true], $params ?? [], ['id' => 'coupon_fake_'.count($this->requestsTo('/v1/coupons'))]),
            $path === '/v1/promotion_codes' => ['id' => 'promo_fake', 'object' => 'promotion_code', 'code' => $params['code'] ?? null, 'active' => true],
            default => ['id' => 'obj_fake', 'object' => 'unknown'],
        };

        return [json_encode($body), 200, []];
    }

    /**
     * @return array<int, array{method: string, path: string, params: array<string, mixed>}>
     */
    public function requestsTo(string $path): array
    {
        return array_values(array_filter($this->requests, fn (array $request) => $request['path'] === $path));
    }
}
