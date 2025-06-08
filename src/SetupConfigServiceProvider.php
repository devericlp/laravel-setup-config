<?php

namespace Devericlp\LaravelSetupConfig;

use Devericlp\LaravelSetupConfig\Console\SetupProjectCommand;
use Illuminate\Support\ServiceProvider;

class SetupConfigServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                SetupProjectCommand::class
            ]);
    }
    }
}