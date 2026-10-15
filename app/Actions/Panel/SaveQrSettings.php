<?php

namespace App\Actions\Panel;

use App\Models\Restaurant;
use App\Support\QrStyle;
use App\Support\RestaurantQr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Saves the dono's QR code and table display choices. Every plan can choose the QR color
 * (it must stay readable on white); the rest — style, logo, display model and texts — only
 * counts on plans with the QR customization feature, and is ignored otherwise.
 */
class SaveQrSettings
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed> the settings as saved
     */
    public function handle(Restaurant $restaurant, array $input): array
    {
        $customizable = RestaurantQr::canCustomize($restaurant);

        $rules = ['color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/', function (string $attribute, mixed $value, \Closure $fail) {
            if (! QrStyle::isReadable((string) $value)) {
                $fail('Essa cor é clara demais para o QR Code ser lido com segurança. Escolha uma mais escura.');
            }
        }]];

        if ($customizable) {
            $rules += [
                'style' => ['required', Rule::in(array_keys(QrStyle::STYLES))],
                'logo' => ['boolean'],
                'template' => ['required', Rule::in(array_keys(RestaurantQr::TEMPLATES))],
                'display_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
                'display_bg' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
                'display_text' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
                'display_font' => ['nullable', Rule::in(array_keys((array) config('feed.fonts')))],
                'headline' => ['required', 'string', 'max:70'],
                'subtitle' => ['nullable', 'string', 'max:90'],
                'show_logo' => ['boolean'],
                'show_name' => ['boolean'],
            ];
        }

        $data = Validator::validate($input, $rules, [
            'color.required' => 'Escolha uma cor para o QR Code.',
            'color.regex' => 'Use uma cor no formato #RRGGBB.',
            'display_color.regex' => 'Use uma cor no formato #RRGGBB.',
            'display_bg.regex' => 'Use uma cor no formato #RRGGBB.',
            'display_text.regex' => 'Use uma cor no formato #RRGGBB.',
            'headline.required' => 'Escreva a chamada principal do display.',
            'headline.max' => 'A chamada pode ter no máximo 70 caracteres.',
            'subtitle.max' => 'O texto de apoio pode ter no máximo 90 caracteres.',
        ]);

        $settings = $customizable
            ? array_merge(RestaurantQr::DEFAULTS, [
                'color' => strtoupper($data['color']),
                'style' => $data['style'],
                'logo' => (bool) ($data['logo'] ?? false) && $restaurant->logo_path !== null,
                'template' => $data['template'],
                'display_color' => ! empty($data['display_color']) ? strtoupper($data['display_color']) : null,
                'display_bg' => ! empty($data['display_bg']) ? strtoupper($data['display_bg']) : null,
                'display_text' => ! empty($data['display_text']) ? strtoupper($data['display_text']) : null,
                'display_font' => ! empty($data['display_font']) ? $data['display_font'] : null,
                'headline' => $this->clean($data['headline']),
                'subtitle' => $this->clean($data['subtitle'] ?? ''),
                'show_logo' => (bool) ($data['show_logo'] ?? false),
                'show_name' => (bool) ($data['show_name'] ?? false),
            ])
            : array_merge(RestaurantQr::DEFAULTS, ['color' => strtoupper($data['color'])]);

        // Letters must be readable on the card: refuse a color that does not contrast with the background.
        if ($customizable && $settings['display_text'] !== null
            && QrStyle::contrast($settings['display_text'], RestaurantQr::background($restaurant, $settings)) < QrStyle::MIN_CONTRAST) {
            throw ValidationException::withMessages(['display_text' => 'Essa cor de letra não contrasta com o fundo do cartão. Escolha uma que se leia bem.']);
        }

        $restaurant->update(['qr_settings' => $settings]);

        return $settings;
    }

    private function clean(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', strip_tags($text)));
    }
}
