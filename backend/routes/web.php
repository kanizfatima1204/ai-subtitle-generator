<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/login', function () {
    return Inertia::render(
        'Auth/Login'
    );
})->name('login');

Route::get('/register', function () {
    return Inertia::render(
        'Auth/Register'
    );
})->name('register');

Route::get('/', function () {
    return Inertia::render(
        'Dashboard'
    );
})->name('dashboard');

Route::get('/history', function () {
    return Inertia::render(
        'JobHistory'
    );
})->name('history');
