<?php

namespace App\Http\Requests\Feed;

use App\Actions\Settings\SaveOrderSettings;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The checkout payload of CT-01. Only the shape is validated here; prices are never read
 * from it (RF-05) and the business rules live in PlaceOrder.
 */
class PlaceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $phone = (string) data_get($this->input('customer'), 'phone', '');

        if ($phone !== '') {
            $normalized = SaveOrderSettings::normalizeWhatsApp($phone);
            $customer = $this->input('customer');
            $customer['phone'] = $normalized ?? $phone;
            $this->merge(['customer' => $customer]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $delivery = $this->input('fulfillment') === 'entrega';

        return [
            'items' => ['required', 'array', 'min:1', 'max:60'],
            'items.*.dish_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
            'items.*.variant_id' => ['nullable', 'integer'],
            'items.*.size_id' => ['nullable', 'integer'],
            'items.*.flavor_ids' => ['nullable', 'array', 'max:4'],
            'items.*.flavor_ids.*' => ['integer'],
            'items.*.options' => ['nullable', 'array', 'max:60'],
            'items.*.options.*.id' => ['required_with:items.*.options', 'integer'],
            'items.*.options.*.quantity' => ['nullable', 'integer', 'min:1', 'max:20'],

            'fulfillment' => ['required', 'in:entrega,retirada'],

            'customer.name' => ['required', 'string', 'max:120'],
            'customer.phone' => ['required', 'string', 'regex:/^55\d{10,11}$/'],

            'address' => [$delivery ? 'required' : 'nullable', 'array'],
            'address.street' => [$delivery ? 'required' : 'nullable', 'string', 'max:150'],
            'address.number' => [$delivery ? 'required' : 'nullable', 'string', 'max:20'],
            'address.complement' => ['nullable', 'string', 'max:80'],
            'address.neighborhood' => ['nullable', 'string', 'max:60'],
            'address.zone_id' => ['nullable', 'integer'],
            'address.lat' => ['nullable', 'numeric', 'between:-90,90'],
            'address.lng' => ['nullable', 'numeric', 'between:-180,180'],
            'address.reference' => ['nullable', 'string', 'max:150'],

            'payment_method' => ['required', 'in:pix,dinheiro,debito,credito'],
            'change_for' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:300'],

            'origin' => ['nullable', 'string', 'max:30'],
            'campaign' => ['nullable', 'string', 'max:60'],
            'promo_code' => ['nullable', 'string', 'max:40'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'O pedido está vazio.',
            'customer.name.required' => 'Informe seu nome.',
            'customer.phone.required' => 'Informe seu WhatsApp.',
            'customer.phone.regex' => 'WhatsApp inválido. Use DDD + número.',
            'address.street.required' => 'Informe a rua.',
            'address.number.required' => 'Informe o número (ou "s/n").',
            'payment_method.required' => 'Escolha a forma de pagamento.',
        ];
    }
}
