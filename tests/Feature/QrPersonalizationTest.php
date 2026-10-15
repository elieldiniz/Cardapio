<?php

use App\Actions\Panel\SaveQrSettings;
use App\Filament\Resources\Plans\Pages\EditPlan;
use App\Models\Plan;
use App\Models\User;
use App\Support\QrStyle;
use App\Support\RestaurantQr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    seedReferenceData();
    Storage::fake('public');
});

function ownerOn(string $plan, bool $withLogo = false): User
{
    $dono = User::factory()->create();
    $dono->restaurant->update(['plan_id' => Plan::where('name', $plan)->value('id')]);

    if ($withLogo) {
        Storage::disk('public')->put('logos/logo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));
        $dono->restaurant->update(['logo_path' => 'logos/logo.png']);
    }

    return $dono->fresh();
}

function fullChoices(array $overrides = []): array
{
    return array_merge([
        'color' => '#0B5D3B', 'style' => 'dots', 'logo' => true, 'template' => 'moldura', 'display_color' => '#6d28d9', 'display_bg' => '#0b2a4a', 'display_text' => '#ffffff', 'display_font' => 'Playfair Display',
        'headline' => 'Veja o prato antes de pedir', 'subtitle' => 'Escaneie e assista',
        'show_logo' => true, 'show_name' => true,
    ], $overrides);
}

it('seeds the qr customization on the paid plans only', function () {
    expect(Plan::where('name', 'Grátis')->value('qr_customization'))->toBeFalse()
        ->and(Plan::where('name', 'Básico')->value('qr_customization'))->toBeTrue()
        ->and(Plan::where('name', 'Pro')->value('qr_customization'))->toBeTrue();
});

it('lets a paid plan save color, style, logo, model and texts', function () {
    $dono = ownerOn('Básico', withLogo: true);

    $saved = app(SaveQrSettings::class)->handle($dono->restaurant, fullChoices(['headline' => '  Veja   o prato <b>antes</b> de pedir ']));

    expect($saved)->toMatchArray(['color' => '#0B5D3B', 'display_color' => '#6D28D9', 'display_bg' => '#0B2A4A', 'display_text' => '#FFFFFF', 'display_font' => 'Playfair Display', 'style' => 'dots', 'logo' => true, 'template' => 'moldura', 'headline' => 'Veja o prato antes de pedir'])
        ->and($dono->restaurant->fresh()->qr_settings['style'])->toBe('dots');
});

it('lets the free plan pick only the qr color and ignores the rest', function () {
    $dono = ownerOn('Grátis', withLogo: true);

    $saved = app(SaveQrSettings::class)->handle($dono->restaurant, fullChoices());

    expect($saved['color'])->toBe('#0B5D3B')
        ->and($saved['style'])->toBe('square')
        ->and($saved['logo'])->toBeFalse()
        ->and($saved['template'])->toBe('classico')
        ->and($saved['display_color'])->toBeNull()
        ->and($saved['display_bg'])->toBeNull()
        ->and($saved['display_text'])->toBeNull()
        ->and($saved['display_font'])->toBeNull()
        ->and($saved['headline'])->toBe(RestaurantQr::DEFAULTS['headline']);

    // Even if stale data sits in the column, the free plan is drawn with the defaults.
    $dono->restaurant->update(['qr_settings' => fullChoices()]);
    expect(RestaurantQr::settings($dono->restaurant->fresh()))->toMatchArray(['style' => 'square', 'template' => 'classico', 'logo' => false, 'color' => '#0B5D3B']);
});

it('refuses a color too light to scan, on every plan', function (string $plan) {
    $dono = ownerOn($plan);

    expect(fn () => app(SaveQrSettings::class)->handle($dono->restaurant, fullChoices(['color' => '#FFE066'])))->toThrow(ValidationException::class);
    expect($dono->restaurant->fresh()->qr_settings)->toBeNull();
})->with(['Grátis', 'Básico']);

it('validates style, model and texts', function (array $bad, string $field) {
    $dono = ownerOn('Pro');

    try {
        app(SaveQrSettings::class)->handle($dono->restaurant, fullChoices($bad));
        $this->fail('should have been refused');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($field);
    }
})->with([
    'unknown style' => [['style' => 'hexagons'], 'style'],
    'unknown model' => [['template' => 'neon'], 'template'],
    'empty headline' => [['headline' => '   '], 'headline'],
    'long headline' => [['headline' => str_repeat('a', 71)], 'headline'],
    'long subtitle' => [['subtitle' => str_repeat('a', 91)], 'subtitle'],
    'not a hex color' => [['color' => 'red'], 'color'],
    'bad display color' => [['display_color' => 'purple'], 'display_color'],
    'bad display background' => [['display_bg' => 'dark'], 'display_bg'],
    'bad letter color' => [['display_text' => 'white'], 'display_text'],
    'unknown font' => [['display_font' => 'Comic Sans'], 'display_font'],
    'letters that do not read on the background' => [['display_bg' => '#FFFFFF', 'display_text' => '#FFE066'], 'display_text'],
]);

it('only puts the logo in the middle when the restaurant has one', function () {
    $withoutLogo = ownerOn('Básico');
    expect(app(SaveQrSettings::class)->handle($withoutLogo->restaurant, fullChoices())['logo'])->toBeFalse();

    $withLogo = ownerOn('Básico', withLogo: true);
    app(SaveQrSettings::class)->handle($withLogo->restaurant, fullChoices());

    expect(RestaurantQr::svg($withLogo->restaurant->fresh()))->toContain('<image')
        ->and(RestaurantQr::svg($withoutLogo->restaurant->fresh()))->not->toContain('<image');
});

it('never changes what the qr code encodes when the style changes', function () {
    $dono = ownerOn('Pro', withLogo: true);
    $url = $dono->restaurant->feedUrl();

    $plain = RestaurantQr::svg($dono->restaurant);
    app(SaveQrSettings::class)->handle($dono->restaurant, fullChoices());

    // The geometry is redrawn, but the encoded address is the restaurant feed URL in both cases.
    expect(RestaurantQr::svg($dono->restaurant->fresh()))->not->toBe($plain)
        ->and(QrStyle::isReadable(RestaurantQr::settings($dono->restaurant->fresh())['color']))->toBeTrue()
        ->and($url)->toBe($dono->restaurant->fresh()->feedUrl());
});

it('shows the panel with the choices loaded and saves each valid change right away', function () {
    $dono = ownerOn('Básico');

    Livewire::actingAs($dono)->test('pages::panel.qr-code')
        ->assertSet('style', 'square')
        ->assertDontSeeHtml('data-qr-locked')
        ->set('style', 'rounded')
        ->set('template', 'escuro')
        ->set('headline', 'Escaneie e veja o prato')
        ->assertHasNoErrors()
        ->assertSeeHtml('data-template="escuro"')
        ->assertSee('Escaneie e veja o prato');

    expect($dono->restaurant->fresh()->qr_settings)->toMatchArray(['style' => 'rounded', 'template' => 'escuro', 'headline' => 'Escaneie e veja o prato']);
});

it('shows the error and does not save when a color is too light', function () {
    $dono = ownerOn('Básico');

    Livewire::actingAs($dono)->test('pages::panel.qr-code')
        ->set('color', '#FFE066')
        ->assertHasErrors('color')
        ->assertSee('clara demais');

    expect($dono->restaurant->fresh()->qr_settings)->toBeNull();
});

it('locks style, logo, models and texts on the free plan and offers the upgrade', function () {
    $dono = ownerOn('Grátis');

    Livewire::actingAs($dono)->test('pages::panel.qr-code')
        ->assertSeeHtml('data-qr-locked')
        ->assertSee('a partir do plano Básico')
        ->assertSeeHtml('data-qr-colors')
        ->set('color', '#1D4ED8')
        ->assertHasNoErrors();

    // A forged request to change a locked option is ignored by the server.
    Livewire::actingAs($dono)->test('pages::panel.qr-code')->set('template', 'escuro')->set('style', 'dots');

    expect($dono->restaurant->fresh()->qr_settings)->toMatchArray(['color' => '#1D4ED8', 'style' => 'square', 'template' => 'classico']);
});

it('prints the display with the chosen model and texts', function () {
    $dono = ownerOn('Básico', withLogo: true);
    app(SaveQrSettings::class)->handle($dono->restaurant, fullChoices(['template' => 'moldura']));

    $this->actingAs($dono)->get(route('panel.qr-code.print', ['mesa' => '7']))
        ->assertOk()
        ->assertSeeHtml('td--moldura')
        ->assertSee('Veja o prato antes de pedir')
        ->assertDontSee('@seu.restaurante')
        ->assertSee('Mesa 7')
        ->assertSeeHtml('data-qr');
});

it('shows our logo and name quietly at the bottom of the display on every plan', function (string $plan) {
    $this->actingAs(ownerOn($plan))->get(route('panel.qr-code.print'))
        ->assertOk()
        ->assertSeeHtml('data-td-brand')
        ->assertSeeHtml('images/brand/symbol.png')
        ->assertSee(config('app.name'));
})->with(['Grátis', 'Básico', 'Pro']);

it('downloads the qr code as an svg file', function () {
    $dono = ownerOn('Básico', withLogo: true);
    app(SaveQrSettings::class)->handle($dono->restaurant, fullChoices());

    $response = $this->actingAs($dono)->get(route('panel.qr-code.svg'))->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('image/svg+xml')
        ->and($response->headers->get('Content-Disposition'))->toContain('qr-code-'.$dono->restaurant->slug.'.svg')
        ->and($response->getContent())->toStartWith('<?xml')->toContain('<svg')->toContain('#0B5D3B');
});

it('lets the super admin switch the qr customization on a plan', function () {
    $this->actingAs(User::factory()->superAdmin()->create());
    $plan = Plan::where('name', 'Grátis')->first();

    Livewire::test(EditPlan::class, ['record' => $plan->getRouteKey()])
        ->fillForm(['qr_customization' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($plan->fresh()->qr_customization)->toBeTrue();
});

it('mentions the personalization on the paid plan cards of the landing', function () {
    config()->set('landing.show_prices', true);

    $content = $this->get('/')->assertOk()->getContent();

    expect(substr_count($content, 'Personalize o QR Code e o display: estilo, logo, modelos e textos'))->toBe(2);
});

it('uses a wider panel for the qr page, with controls, preview and code side by side', function () {
    $this->actingAs(ownerOn('Básico'))->get(route('panel.qr-code'))
        ->assertOk()
        ->assertSeeHtml('max-w-none')
        ->assertSeeHtml('data-qr-layout')
        ->assertSeeHtml('data-qr-preview-col')
        ->assertSeeHtml('data-qr-code-col');

    $this->get(route('panel.home'))->assertSeeHtml('max-w-[980px]')->assertDontSeeHtml('max-w-none');
});

it('lets a paid plan pick the display color and uses it on the card, with readable text on top', function () {
    $dono = ownerOn('Básico');

    // Any color works for the card (a light yellow too): the text on it adapts instead of being refused.
    app(SaveQrSettings::class)->handle($dono->restaurant, fullChoices(['template' => 'moldura', 'display_color' => '#FFE066', 'logo' => false]));

    $this->actingAs($dono)->get(route('panel.qr-code.print'))
        ->assertOk()
        ->assertSeeHtml('--td-accent: #FFE066')
        ->assertSeeHtml('--td-on-accent: #1A1815');

    // Back to the restaurant color when cleared.
    app(SaveQrSettings::class)->handle($dono->restaurant->fresh(), fullChoices(['display_color' => null, 'logo' => false]));

    // A fresh user instance: the one in memory still holds the restaurant it loaded for the first request.
    $this->actingAs($dono->fresh())->get(route('panel.qr-code.print'))->assertSeeHtml('--td-accent: '.$dono->restaurant->fresh()->accentColor());
});

it('keeps the display in the restaurant color on the free plan and offers the choice on paid ones', function () {
    $free = ownerOn('Grátis');
    $free->restaurant->update(['qr_settings' => fullChoices()]);

    $this->actingAs($free)->get(route('panel.qr-code.print'))->assertSeeHtml('--td-accent: '.$free->restaurant->accentColor());

    Livewire::actingAs(ownerOn('Pro'))->test('pages::panel.qr-code')
        ->assertSeeHtml('data-display-colors')
        ->set('displayColor', '#15803D')
        ->assertHasNoErrors()
        ->assertSeeHtml('--td-accent: #15803D');
});

it('lets a paid plan change the inside background of the card, with text and tags adapting to it', function () {
    $dono = ownerOn('Básico');

    // Light background: dark text. Dark background: white text. The QR stays on white either way.
    app(SaveQrSettings::class)->handle($dono->restaurant, fullChoices(['template' => 'classico', 'display_bg' => '#FBF3E4', 'display_color' => '#6D28D9', 'display_text' => null, 'logo' => false]));

    $this->actingAs($dono)->get(route('panel.qr-code.print'))
        ->assertOk()
        ->assertSeeHtml('--td-bg: #FBF3E4')
        ->assertSeeHtml('--td-fg: #1A1815')
        ->assertSeeHtml('--td-tag-bg: #6D28D9');

    app(SaveQrSettings::class)->handle($dono->restaurant->fresh(), fullChoices(['template' => 'classico', 'display_bg' => '#0B2A4A', 'display_text' => null, 'logo' => false]));

    $this->actingAs($dono->fresh())->get(route('panel.qr-code.print'))
        ->assertSeeHtml('--td-bg: #0B2A4A')
        ->assertSeeHtml('--td-fg: #FFFFFF');
});

it('falls back to the model background when none is chosen and keeps the table tag white on a card in the main color', function () {
    $dono = ownerOn('Básico');
    app(SaveQrSettings::class)->handle($dono->restaurant, fullChoices(['template' => 'moldura', 'display_bg' => null, 'display_color' => '#B91C1C', 'logo' => false]));

    $this->actingAs($dono)->get(route('panel.qr-code.print', ['mesa' => '3']))
        ->assertSeeHtml('--td-bg: #B91C1C')
        ->assertSeeHtml('--td-tag-bg: #FFFFFF');
});

it('ignores the background choice on the free plan and shows the picker on paid ones', function () {
    $free = ownerOn('Grátis');
    $free->restaurant->update(['qr_settings' => fullChoices()]);

    $this->actingAs($free)->get(route('panel.qr-code.print'))->assertSeeHtml('--td-bg: #FFFFFF');

    Livewire::actingAs(ownerOn('Pro'))->test('pages::panel.qr-code')
        ->assertSeeHtml('data-display-bg')
        ->set('displayBg', '#14452F')
        ->assertHasNoErrors()
        ->assertSeeHtml('--td-bg: #14452F');
});

it('lets a paid plan pick the letter color and font, used on the card and loaded on the print page', function () {
    $dono = ownerOn('Básico');
    app(SaveQrSettings::class)->handle($dono->restaurant, fullChoices(['template' => 'escuro', 'display_bg' => '#161412', 'display_text' => '#CA8A04', 'display_font' => 'Bebas Neue', 'logo' => false]));

    $this->actingAs($dono)->get(route('panel.qr-code.print'))
        ->assertOk()
        ->assertSeeHtml('--td-fg: #CA8A04')
        ->assertSeeHtml("--td-font: 'Bebas Neue'")
        ->assertSeeHtml('fonts.bunny.net/css?family=bebas-neue');
});

it('falls back to automatic letters when the background changed and the chosen color no longer reads', function () {
    $dono = ownerOn('Pro');
    $dono->restaurant->update(['qr_settings' => fullChoices(['template' => 'classico', 'display_bg' => '#161412', 'display_text' => '#1A1815', 'logo' => false])]);

    // Dark letters on a dark card would be unreadable: the card shows automatic (white) letters.
    $this->actingAs($dono->fresh())->get(route('panel.qr-code.print'))->assertSeeHtml('--td-fg: #FFFFFF');
});

it('resets the letter color on the panel when a new background would make it unreadable', function () {
    $dono = ownerOn('Pro');

    Livewire::actingAs($dono)->test('pages::panel.qr-code')
        ->set('displayBg', '#FBF3E4')
        ->set('displayText', '#1A1815')
        ->assertHasNoErrors()
        ->set('displayBg', '#161412')
        ->assertSet('displayText', '')
        ->assertHasNoErrors();

    expect($dono->restaurant->fresh()->qr_settings['display_bg'])->toBe('#161412')
        ->and($dono->restaurant->fresh()->qr_settings['display_text'])->toBeNull();
});

it('refuses on the panel a letter color that does not read on the card', function () {
    $dono = ownerOn('Básico');

    Livewire::actingAs($dono)->test('pages::panel.qr-code')
        ->assertSeeHtml('data-display-letters')
        ->assertSeeHtml('data-display-fonts')
        ->set('displayText', '#FFFFFF')
        ->assertHasErrors('display_text')
        ->assertSee('não contrasta');
});

it('ignores letter color and font on the free plan', function () {
    $free = ownerOn('Grátis');
    $free->restaurant->update(['qr_settings' => fullChoices()]);

    $this->actingAs($free)->get(route('panel.qr-code.print'))
        ->assertSeeHtml('--td-fg: #1A1815')
        ->assertDontSeeHtml('--td-font:');
});
