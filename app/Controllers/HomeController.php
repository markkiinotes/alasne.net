<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

class HomeController extends Controller
{
    public function index()
    {
        return $this->view('home.index', [
            'title' => config('app.name'),
            'message' => 'Config Repository is working.',
        ]);
    }
}