<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\CountryResource;

class CountryController extends Controller
{
    public function index()
    {
        $countriesJson = file_get_contents(database_path('data/countries.json'));

        $countries = json_decode($countriesJson, true);

        return $this->json('all countries', [
            'countries' => CountryResource::collection($countries),
        ]);
    }
}
