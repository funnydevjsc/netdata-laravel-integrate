<?php

namespace FunnyDev\Netdata;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;

class NetdataServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/netdata.php' => config_path('netdata.php'),
            __DIR__.'/../app/Console/Commands/NetdataCommand.php' => app_path('Console/Commands/NetdataCommand.php')
        ], 'netdata');

        try {
            if (!file_exists(config_path('netdata.php'))) {
                $this->commands([
                    \Illuminate\Foundation\Console\VendorPublishCommand::class,
                ]);

                Artisan::call('vendor:publish', ['--provider' => 'FunnyDev\\Netdata\\NetdataServiceProvider', '--tag' => ['netdata']]);
            }
        } catch (\Exception $e) {}
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/netdata.php', 'netdata'
        );
        $this->app->singleton(\FunnyDev\Netdata\NetdataSdk::class, function ($app) {
            $server = $app['config']['netdata.server'];
            $version = $app['config']['netdata.version'];
            $scope_node = $app['config']['netdata.scope_node'];
            $username = $app['config']['netdata.username'];
            $password = $app['config']['netdata.password'];
            return new \FunnyDev\Netdata\NetdataSdk($server, $version, $scope_node, $username, $password);
        });
    }
}
