<?php

namespace Nurdin73\PdfStamper\Facades;

use Illuminate\Support\Facades\Facade;
use Nurdin73\PdfStamper\PdfStamper as PdfStamperService;

class PdfStamper extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor()
    {
        return PdfStamperService::class;
    }
}
