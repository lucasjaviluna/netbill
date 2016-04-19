<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;

class AuthenticationController extends Controller
{
    public function index(Request $request) {
        var_dump($request->all());
        die();
    }
}
