<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected Request $request;

    protected Response $response;

    public function __construct()
    {
        $this->request = new Request();
        $this->response = new Response();
    }

    protected function view(string $view, array $data = [], string $layout = 'main'): string
	{
		return View::render($view, $data, $layout);
	}
}