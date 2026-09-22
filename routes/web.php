<?php

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
| Painel do restaurante (dono)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'owner'])->prefix('painel')->name('panel.')->group(function () {
    Route::livewire('/', 'pages::panel.home')->name('home');
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
