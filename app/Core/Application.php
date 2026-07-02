<?php

declare(strict_types=1);

namespace App\Core;

use App\Providers\AppServiceProvider;

class Application
{
    public Container $container;

    public Router $router;

    public Request $request;

    public Response $response;

    protected array $providers = [];

    public function __construct()
    {
        $this->container = new Container();
        $this->request = new Request();
        $this->response = new Response();
        $this->router = new Router();

        $this->registerProviders();
        $this->bootProviders();
    }

    protected function registerProviders(): void
    {
        $this->providers = [
            new AppServiceProvider($this),
        ];

        foreach ($this->providers as $provider) {
            $provider->register();
        }
    }

    protected function bootProviders(): void
    {
        foreach ($this->providers as $provider) {
            $provider->boot();
        }
    }

    public function run(): void
    {
        echo $this->router->resolve();
    }
}