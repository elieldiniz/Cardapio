<?php

namespace App\Actions\Customers;

use App\Models\Customer;
use App\Models\Receivable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Apagar dados" (RF-19, RNF-08): removes everything that identifies a customer — name and
 * WhatsApp on the customer, on every one of their orders and on their paid or cancelled fiado.
 * Amounts, items and dates stay, so the reports and the caixa keep adding up. A customer who
 * still owes a fiado cannot be erased until it is settled or cancelled. Running it twice is harmless.
 */
class EraseCustomerData
{
    public const ERASED_NAME = 'Cliente removido';

    /**
     * @throws ValidationException
     */
    public function handle(Customer $customer): Customer
    {
        if ($customer->erased_at !== null) {
            return $customer;
        }

        $owes = Receivable::query()->where('customer_id', $customer->id)->whereNull('paid_at')->whereNull('cancelled_at')->exists();

        if ($owes) {
            throw ValidationException::withMessages(['customer' => 'Este cliente ainda tem fiado em aberto. Dê baixa ou cancele antes de apagar os dados.']);
        }

        return DB::transaction(function () use ($customer) {
            $customer->orders()->each(function ($order) {
                $order->items()->update(['notes' => null]);
                $order->update([
                    'customer_name' => self::ERASED_NAME,
                    'customer_phone' => null,
                    'address_street' => null,
                    'address_number' => null,
                    'address_complement' => null,
                    'neighborhood' => null,
                    'address_reference' => null,
                    'notes' => null,
                    'personal_data_purged_at' => $order->personal_data_purged_at ?? now(),
                ]);
            });

            Receivable::query()->where('customer_id', $customer->id)->update(['customer_name' => self::ERASED_NAME, 'customer_phone' => null]);

            $customer->update(['name' => null, 'whatsapp' => null, 'erased_at' => now()]);

            return $customer;
        });
    }
}
