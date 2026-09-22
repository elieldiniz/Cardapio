<?php

use App\Http\Controllers\Auth\LogoutController;
use App\Support\Dev\FeedPreview;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

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

/*
|--------------------------------------------------------------------------
| Painel do restaurante (dono)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'auth.session', 'owner'])->prefix('painel')->name('panel.')->group(function () {
    Route::livewire('/', 'pages::panel.home')->name('home');
    Route::livewire('/categorias', 'pages::panel.categories')->name('categories');
    Route::livewire('/pratos', 'pages::panel.dishes')->name('dishes');
    Route::livewire('/pratos/novo', 'pages::panel.dish-form')->name('dishes.create');
    Route::livewire('/pratos/{dish}/editar', 'pages::panel.dish-form')->name('dishes.edit');
});

/*
|--------------------------------------------------------------------------
| Dev-only component preview
|--------------------------------------------------------------------------
*/

if (app()->environment(['local', 'testing'])) {
    Route::view('/dev/components', 'dev.components')->name('dev.components');
    Route::get('/dev/feed', fn () => view('feed.show', FeedPreview::page()))->name('dev.feed');
    Route::get('/dev/feed/categorias/{category}', fn (string $category) => [
        'dishes' => FeedPreview::dishes($category),
    ])->name('dev.feed.category');
}
