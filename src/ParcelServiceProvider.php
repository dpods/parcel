<?php

namespace Dpods\Parcel;

use Dpods\Parcel\Commands\MakePackage;
use Illuminate\Support\ServiceProvider;

class ParcelServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/parcel.php' => config_path('parcel.php')
            ], 'config');

            $this->commands([
                MakePackage::class,
            ]);
        }
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/parcel.php', 'parcel');
    }
}
