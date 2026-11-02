<?php

use App\Actions\Admin\Impersonation;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\CourierLinkController;
use App\Http\Controllers\Feed\DishViewController;
use App\Http\Controllers\Feed\FeedController;
use App\Http\Controllers\Feed\FunnelEventController;
use App\Http\Controllers\Feed\OrderController;
use App\Http\Controllers\Feed\OrderTrackingController;
use App\Http\Controllers\Panel\AppearancePreviewController;
use App\Http\Controllers\Panel\QrCodeController;
use App\Http\Controllers\Panel\TicketController;
use App\Http\Controllers\PrivacyController;
use App\Http\Controllers\Webhooks\MuxWebhookController;
use App\Models\Plan;
use App\Models\VideoAddonPackage;
use App\Support\FeedSample;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Site público (landing e páginas institucionais)
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('marketing.home', [
        'plans' => Plan::query()->where('is_active', true)->with('metricsLevel')->orderBy('price_cents')->get(),
        'packages' => VideoAddonPackage::query()->where('is_active', true)->get(),
    ]);
})->name('home');

Route::view('/sobre', 'marketing.about')->name('marketing.about');
Route::view('/contato', 'marketing.contact')->name('marketing.contact');
Route::view('/termos', 'marketing.legal.terms')->name('legal.terms');
Route::get('/privacidade', PrivacyController::class)->name('legal.privacy');
Route::view('/reembolso', 'marketing.legal.refund')->name('legal.refund');
Route::view('/cookies', 'marketing.legal.cookies')->name('legal.cookies');

Route::get('/smoke-test/livewire', function () {
    return view('smoke-test-page');
});

/*
|--------------------------------------------------------------------------
| Autenticação (US-8.1–US-8.4)
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::livewire('/cadastro', 'pages::auth.register')->name('register');
    Route::livewire('/entrar', 'pages::auth.login')->name('login');
    Route::livewire('/esqueci-senha', 'pages::auth.forgot-password')->name('password.request');
    Route::livewire('/redefinir-senha/{token}', 'pages::auth.reset-password')->name('password.reset');
});

Route::post('/sair', LogoutController::class)->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::livewire('/email/verificar', 'pages::auth.verify-email')->name('verification.notice');
    Route::get('/email/verificar/{id}/{hash}', function (EmailVerificationRequest $request) {
        $request->fulfill();
        session()->flash('toast', ['message' => 'E-mail confirmado!', 'type' => 'success']);

        return redirect()->route('panel.home');
    })->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
});

Route::post('/impersonacao/encerrar', function (Impersonation $impersonation) {
    return $impersonation->stop() ? redirect('/admin') : redirect()->route('login');
})->middleware('auth')->name('impersonation.stop');

/*
|--------------------------------------------------------------------------
| Feed público do cliente (US-1.1–US-1.4) — sem login
|--------------------------------------------------------------------------
*/

Route::prefix('r/{restaurant:slug}')->name('feed.')->group(function () {
    Route::get('/', [FeedController::class, 'show'])->name('show');
    Route::get('/categorias/{category}', [FeedController::class, 'category'])->name('category');
    Route::post('/visualizacoes', [DishViewController::class, 'store'])
        ->middleware('throttle:120,1')
        ->name('views');
    Route::post('/pedidos', [OrderController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('orders.store');
    Route::post('/eventos', [FunnelEventController::class, 'store'])
        ->middleware('throttle:120,1')
        ->name('events');
    Route::post('/cupom', [OrderController::class, 'coupon'])
        ->middleware('throttle:30,1')
        ->name('orders.coupon');
    Route::post('/pedidos/{token}/whatsapp', [OrderController::class, 'whatsapp'])
        ->middleware('throttle:60,1')
        ->name('orders.whatsapp');
});

// Customer tracking page (CT-03): public, keyed only by the unguessable token.
// Courier link (CT-05): only the token opens it; the dono can revoke it.
Route::get('/entregador/{token}', [CourierLinkController::class, 'show'])->middleware('throttle:60,1')->name('courier.show');
Route::post('/entregador/{token}/{order}/entregue', [CourierLinkController::class, 'deliver'])->middleware('throttle:60,1')->name('courier.deliver');

Route::get('/p/{token}', [OrderTrackingController::class, 'show'])->middleware('throttle:60,1')->name('order.track');
Route::get('/p/{token}/status', [OrderTrackingController::class, 'status'])->middleware('throttle:120,1')->name('order.track.status');

/*
|--------------------------------------------------------------------------
| Painel do restaurante (dono)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'auth.session', 'owner', 'verified.owner'])->prefix('painel')->name('panel.')->group(function () {
    Route::livewire('/', 'pages::panel.home')->name('home');
    Route::livewire('/categorias', 'pages::panel.categories')->name('categories');
    Route::livewire('/pratos', 'pages::panel.dishes')->name('dishes');
    Route::livewire('/pratos/novo', 'pages::panel.dish-form')->name('dishes.create');
    Route::livewire('/pratos/{dish}/editar', 'pages::panel.dish-form')->name('dishes.edit');
    Route::livewire('/pratos/{dish}/video', 'pages::panel.dish-video')->name('dishes.video');
    Route::livewire('/assinatura', 'pages::panel.subscription')->name('subscription');
    Route::livewire('/aparencia', 'pages::panel.appearance')->name('appearance');
    Route::get('/aparencia/previa', AppearancePreviewController::class)->name('appearance.preview');
    Route::livewire('/qr-code', 'pages::panel.qr-code')->name('qr-code');
    Route::get('/qr-code/imprimir', [QrCodeController::class, 'print'])->name('qr-code.print');
    Route::get('/qr-code/svg', [QrCodeController::class, 'svg'])->name('qr-code.svg');
    Route::livewire('/visualizacoes', 'pages::panel.views')->name('views');
    Route::livewire('/suporte', 'pages::panel.support')->name('support');
    Route::livewire('/suporte/{ticket}', 'pages::panel.support-ticket')->name('support.show');
    Route::livewire('/configuracoes', 'pages::panel.settings')->name('settings');
    Route::livewire('/complementos', 'pages::panel.option-groups')->name('option-groups');
    Route::livewire('/pedidos', 'pages::panel.orders')->middleware('plan.feature:orders')->name('orders');
    Route::livewire('/pdv', 'pages::panel.pdv')->middleware('plan.feature:pdv')->name('pdv');
    Route::livewire('/caixa', 'pages::panel.cash')->middleware('plan.feature:cash')->name('cash');
    Route::livewire('/fiado', 'pages::panel.receivables')->middleware('plan.feature:cash')->name('receivables');
    Route::livewire('/relatorio', 'pages::panel.report')->middleware('plan.feature:cash')->name('report');
    Route::livewire('/cupons', 'pages::panel.promo-codes')->middleware('plan.feature:promo_codes')->name('promo-codes');
    Route::livewire('/fidelidade', 'pages::panel.loyalty')->middleware('plan.feature:loyalty')->name('loyalty');
    Route::livewire('/clientes', 'pages::panel.customers')->middleware('plan.feature:orders')->name('customers');
    Route::get('/comanda-teste', [TicketController::class, 'sample'])->middleware('plan.feature:orders')->name('ticket.sample');
    Route::get('/pedidos/{order}/comanda', [TicketController::class, 'show'])->middleware('plan.feature:orders')->name('ticket');
    Route::livewire('/motoboys', 'pages::panel.couriers')->middleware('plan.feature:couriers')->name('couriers');
    Route::livewire('/ajuda', 'pages::panel.help')->name('help');
    Route::livewire('/recurso/{feature}', 'pages::panel.locked')->name('locked');
    Route::livewire('/conta', 'pages::panel.account')->name('account');
});

/*
|--------------------------------------------------------------------------
| Webhooks (signature-verified, CSRF-exempt)
|--------------------------------------------------------------------------
*/

Route::post('/webhooks/mux', MuxWebhookController::class)->name('webhooks.mux');

/*
|--------------------------------------------------------------------------
| Dev-only component preview
|--------------------------------------------------------------------------
*/

if (app()->environment(['local', 'testing'])) {
    Route::view('/dev/components', 'dev.components')->name('dev.components');
    Route::get('/dev/feed', fn () => view('feed.show', FeedSample::page()))->name('dev.feed');
    Route::get('/dev/feed/categorias/{category}', fn (string $category) => [
        'dishes' => FeedSample::dishes($category),
    ])->name('dev.feed.category');
}
