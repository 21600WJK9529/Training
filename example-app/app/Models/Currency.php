<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    public static $forexRates = [];
    public static $cryptoRates = [];

    public static function getRates()
    {
        $url = "https://www.completeapi.com/free_currencies.min.json";

        try {
            $response = Http::get($url);
            $data = $response->json();
            if (isset($data['forex'])) {
                self::$forexRates = $data['forex'];
            } else {
                echo "Error: Unable to fetch forex rates from the API.\n";
            }

            if (isset($data['crypto'])) {
                self::$cryptoRates = $data['crypto'];
            } else {
                echo "Error: Unable to fetch crypto rates from the API.\n";
            }
        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
        finally {
            return [$data['forex'], $data['crypto']];
        }
    }
}