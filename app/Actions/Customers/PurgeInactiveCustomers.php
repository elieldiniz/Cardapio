<?php

namespace App\Actions\Customers;

use App\Models\Customer;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * Erases the customers whose last order is more than two years old (RNF-08). A customer with a
 * fiado still open is kept, and so is anyone who ordered recently.
 */
class PurgeInactiveCustomers
{
    public const RETENTION_YEARS = 2;

    public function __construct(private readonly EraseCustomerData $erase) {}

    /**
     * @return int how many customers were erased
     */
    public function handle(?CarbonInterface $now = null): int
    {
        $limit = ($now ?? now())->copy()->subYears(self::RETENTION_YEARS);
        $erased = 0;

        Customer::query()
            ->whereNull('erased_at')
            ->where('last_order_at', '<', $limit)
            ->orderBy('id')
            ->each(function (Customer $customer) use (&$erased) {
                try {
                    $this->erase->handle($customer);
                    $erased++;
                } catch (ValidationException) {
                    // Still owes a fiado: kept until it is settled.
                }
            });

        return $erased;
    }
}
