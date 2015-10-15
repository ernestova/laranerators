<?php

namespace ErnestoVargas\Generators;

use Illuminate\Support\ServiceProvider;

class GeneratorsProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        $this->app->singleton('command.make.models', function ($app) {
            return $app['ErnestoVargas\Generators\Commands\MakeModelsCommand'];
        });

        $this->commands('command.make.models');

        $this->app->singleton('command.make.owladmins', function ($app) {
            return $app['ErnestoVargas\Generators\Commands\MakeOwladminsCommand'];
        });

        $this->commands('command.make.owladmins');

        $this->app->singleton('command.make.dingo', function ($app) {
            return $app['ErnestoVargas\Generators\Commands\MakeDingoCommand'];
        });

        $this->commands('command.make.dingo');
    }
}
