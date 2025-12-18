<?php

namespace Nurdin73\PdfStamper\Tests;

use Nurdin73\PdfStamper\PdfStamperServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            PdfStamperServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'PdfStamper' => \Nurdin73\PdfStamper\Facades\PdfStamper::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // Set up config untuk testing
        $app['config']->set('pdf-stamper', require __DIR__ . '/../config/pdf-stamper.php');
    }

    protected function getTestFixturePath(string $filename): string
    {
        return __DIR__ . '/fixtures/' . $filename;
    }

    protected function getTestOutputPath(string $filename): string
    {
        $outputDir = __DIR__ . '/output';

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        return $outputDir . '/' . $filename;
    }
}
