<?php

namespace Nurdin73\PdfStamper;

use Illuminate\Support\ServiceProvider;

class PdfStamperServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/pdf-stamper.php',
            'pdf-stamper'
        );

        $this->app->singleton(PdfStamper::class, function () {
            return PdfStamper::instance();
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/pdf-stamper.php' => config_path('pdf-stamper.php'),
        ], 'pdf-stamper-config');
    }
}
