<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
class Currency extends Model
{
    use CrudTrait;
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'rate_usd',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'rate_usd' => 'decimal:4',
    ];

    public static function setRate()
    {
        try {
            // Set the default date to today if not provided
            $date = now()->format('Y-m-d');

            // API URL
            $url = "https://nbg.gov.ge/gw/api/ct/monetarypolicy/currencies/ka/json/?date={$date}";

            // Fetch the JSON data
            $response = Http::get($url);

            // Check for successful response
            if ($response->failed()) {
                return null;
            }
            $data = $response->json();

            // Extract USD to GEL rate
            foreach ($data[0]['currencies'] as $currency) {
                if ($currency['code'] === 'USD') {
                    self::create([
                        'rate_usd' => $currency['rate'],
                    ]);
                    return $currency['rate'];
                }
            }
            // Return null if USD rate not found
            return null;
        } catch (\Exception $e) {
            // Handle errors
            return null;
        }
    }

    public static function exchangeRate() 
    {
        return self::latest()->first()->rate_usd;
    }

    public static function getLastRate()
    {
        return self::latest()->first()->rate_usd;
    }

    public static function getLastRateDate()
    {
        return self::latest()->first()->created_at;
    }
}
