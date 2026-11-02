<?php

namespace App\Console\Commands;

use App\Actions\Orders\PurgeOrderPersonalData;
use Illuminate\Console\Command;

/**
 * Daily: clears address and notes of orders that ended 30 days ago or more (RNF-08).
 */
class PruneOrderPersonalData extends Command
{
    protected $signature = 'orders:prune-personal-data';

    protected $description = 'Clear the address and notes of orders that ended more than 30 days ago';

    public function handle(PurgeOrderPersonalData $purge): int
    {
        $count = $purge->handle();

        $this->info("Pedidos com dados pessoais apagados: {$count}.");

        return self::SUCCESS;
    }
}
