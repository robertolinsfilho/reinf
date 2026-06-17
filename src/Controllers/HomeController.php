<?php

namespace App\Controllers;

class HomeController extends BaseController
{
    public function index(): void
    {
        if ($this->isLoggedIn()) {
            $this->redirect('/dashboard');
        }
        $this->redirect('/login');
    }
}
