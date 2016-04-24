<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('home', function () {
    return view('welcome');
});

Route::get('login', function () {
    return view('auth.login');
});

Route::get('register', function () {
    return view("auth.register");
});

Route::get('dashboard', function () {
    return view('dashboard');
});

Route::resource('auth', 'AuthenticationController');
Route::post('login', 'AuthenticationController@login');
Route::post('register', 'AuthenticationController@register');
Route::get('logout', 'AuthenticationController@logout');