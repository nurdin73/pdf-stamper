<?php

namespace KarungBolong\PdfStamper\Facades;

use Illuminate\Support\Facades\Facade;
use KarungBolong\PdfStamper\PdfStamper as PdfStamperService;

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
