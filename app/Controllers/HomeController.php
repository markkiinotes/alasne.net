<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

class HomeController extends Controller
{
    public function index()
    {
        return $this->view('home.index', [
            'title' => 'Welcome to Alasne Framework',
            'message' => 'The custom PHP framework is now rendering views.',
        ]);
    }
}