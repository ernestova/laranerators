<?php

namespace ErnestoVargas\Laranerator;

use Illuminate\Support\ServiceProvider;

class ModelGeneratorProvider extends ServiceProvider
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
            return $app['ErnestoVargas\Laranerators\Commands\MakeModelsCommand'];
        });

        $this->commands('command.make.models');

        $this->app->singleton('command.make.owladmins', function ($app) {
            return $app['ErnestoVargas\Laranerators\Commands\MakeOwladminsCommand'];
        });

        $this->commands('command.make.owladmins');

        $this->app->singleton('command.make.dingo', function ($app) {
            return $app['ErnestoVargas\Laranerators\Commands\MakeDingoCommand'];
        });

        $this->commands('command.make.dingo');
    }
}
