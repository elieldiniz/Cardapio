<?php

namespace App\Console\Commands;

use App\Actions\Customers\PurgeInactiveCustomers;
use Illuminate\Console\Command;

/**
 * Daily: erases the personal data of customers with no order for two years (RNF-08).
 */
class PruneInactiveCustomers extends Command
{
    protected $signature = 'customers:prune-inactive';

    protected $description = 'Erase the personal data of customers whose last order is more than 2 years old';

    public function handle(PurgeInactiveCustomers $purge): int
    {
        $this->info('Clientes apagados: '.$purge->handle().'.');

        return self::SUCCESS;
    }
}
