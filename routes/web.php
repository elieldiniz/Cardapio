<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/smoke-test/livewire', function () {
    return view('smoke-test-page');
});
