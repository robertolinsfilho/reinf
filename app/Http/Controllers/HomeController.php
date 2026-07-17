<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class HomeController extends Controller
{
    public function index(): RedirectResponse
    {
        if (auth()->check()) {
            return redirect('/dashboard');
        }
        return redirect('/login');
    }
}
