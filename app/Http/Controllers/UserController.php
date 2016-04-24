<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests;

class UserController extends Controller {

    public function login(LoginRequest $request) {
        if (Auth::attempt(['email' => $request['email'], 'password' => $request['password']])) {
            return Redirect::to('admin');
        }

        Session::flash('message-error', 'Login incorrecto');
        return Redirect::to('/');
    }

    public function logout() {
        Auth::logout();
        return Redirect::to('/');
    }

    public function profile() {
        
    }

    public function register() {
        
    }

    public function registerAdministrator() {
        
    }
}
