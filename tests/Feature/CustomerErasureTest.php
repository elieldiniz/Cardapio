<?php

use App\Actions\Customers\EraseCustomerData;
use App\Actions\Customers\PurgeInactiveCustomers;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Receivable;
use App\Models\RestaurantOrderSetting;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/**
 * RF-19, RNF-08 — the customers list, "Apagar dados" and the two-year purge.
 */
beforeEach(function () {
    seedReferenceData();
    $this->dono = User::factory()->create();
    $this->store = $this->dono->restaurant;
    RestaurantOrderSetting::factory()->create(['restaurant_id' => $this->store->id, 'whatsapp_number' => '5569996041447']);
    $this->dish = feedDish($this->store, ['name' => 'X-Bacon', 'price' => '50.00'], Category::factory()->create(['restaurant_id' => $this->store->id]));
    $this->place = fn (string $phone = '(69) 99999-0000', string $name = 'Ana Souza') => $this->postJson(route('feed.orders.store', $this->store), [
        'items' => [['dish_id' => $this->dish->id, 'quantity' => 1, 'notes' => 'sem cebola']], 'fulfillment' => 'entrega',
        'address' => ['street' => 'Rua A', 'number' => '1', 'neighborhood' => 'Centro'],
        'customer' => ['name' => $name, 'phone' => $phone], 'payment_method' => 'pix', 'notes' => 'tocar a campainha',
    ])->assertCreated();
});

it('erases name and WhatsApp from the customer and from all their orders, keeping the amounts', function () {
    ($this->place)();
    ($this->place)();
    $customer = Customer::sole();

    app(EraseCustomerData::class)->handle($customer);
    app(EraseCustomerData::class)->handle($customer->fresh());

    expect($customer->fresh())->name->toBeNull()->whatsapp->toBeNull()->erased_at->not->toBeNull();

    Order::all()->each(fn ($order) => expect($order)->customer_name->toBe('Cliente removido')->customer_phone->toBeNull()->address_street->toBeNull()->notes->toBeNull()->total->toBe('50.00'));

    expect(json_encode(Order::all()->toArray()))->not->toContain('99999-0000')->not->toContain('Ana');
});

it('creates a new customer when the same phone orders again after erasing', function () {
    ($this->place)();
    app(EraseCustomerData::class)->handle(Customer::sole());

    ($this->place)();

    expect(Customer::count())->toBe(2)->and(Customer::whereNull('erased_at')->sole()->name)->toBe('Ana Souza');
});

it('refuses to erase a customer who still owes a fiado, and anonymizes a settled one', function () {
    ($this->place)();
    $customer = Customer::sole();
    $receivable = Receivable::create(['restaurant_id' => $this->store->id, 'order_id' => Order::sole()->id, 'customer_id' => $customer->id, 'customer_name' => 'Ana Souza', 'customer_phone' => '5569999990000', 'amount' => '50.00', 'reference' => 'r1']);

    expect(fn () => app(EraseCustomerData::class)->handle($customer))->toThrow(ValidationException::class);
    expect($customer->fresh()->erased_at)->toBeNull();

    $receivable->update(['paid_at' => now()]);
    app(EraseCustomerData::class)->handle($customer);

    expect($receivable->fresh())->customer_name->toBe('Cliente removido')->customer_phone->toBeNull();
});

it('purges only customers whose last order is older than two years', function () {
    ($this->place)('(69) 99999-0001', 'Antiga');
    ($this->place)('(69) 99999-0002', 'Recente');
    Customer::where('name', 'Antiga')->update(['last_order_at' => now()->subYears(2)->subDay()]);
    Customer::where('name', 'Recente')->update(['last_order_at' => now()->subYears(2)->addDay()]);

    expect(app(PurgeInactiveCustomers::class)->handle())->toBe(1)->and(app(PurgeInactiveCustomers::class)->handle())->toBe(0);

    expect(Customer::whereNull('erased_at')->sole()->name)->toBe('Recente');
});

it('runs from the artisan command', function () {
    ($this->place)();
    Customer::query()->update(['last_order_at' => now()->subYears(3)]);

    $this->artisan('customers:prune-inactive')->expectsOutput('Clientes apagados: 1.')->assertSuccessful();
});

it('lists, searches and erases from the panel without touching other stores', function () {
    ($this->place)();
    $other = User::factory()->create();
    $stranger = Customer::create(['restaurant_id' => $other->restaurant_id, 'name' => 'Estranho', 'whatsapp' => '5569988887777']);

    $page = Livewire::actingAs($this->dono)->test('pages::panel.customers')->assertSee('Ana Souza')->assertDontSee('Estranho');
    $page->set('search', 'zzz')->assertDontSee('Ana Souza')->set('search', '99999')->assertSee('Ana Souza');

    $page->call('confirmErase', Customer::where('name', 'Ana Souza')->value('id'))->call('erase')->assertDontSee('Ana Souza');

    expect($stranger->fresh()->erased_at)->toBeNull();
});
