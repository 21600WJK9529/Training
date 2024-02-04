<?php

namespace Tests\Unit;

use App\Models\Currency;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CurrencyTest extends TestCase
{
    public function testGetRates()
    {
        // Call the getRates method
        $currencyModel = Currency::getRates();

        // Output the response for inspection
        dd($currencyModel);
    }
}
