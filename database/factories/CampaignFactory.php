<?php

use Faker\Generator as Faker;
use App\Models\Campaign;
use App\Models\Company;
use App\Models\PhoneNumber;
use Carbon\Carbon;

/*
|--------------------------------------------------------------------------
| Model Factories
|--------------------------------------------------------------------------
|
| This directory should contain each of the model factory definitions for
| your application. Factories provide a convenient way to generate new
| model instances for testing / seeding your application's database.
|
*/

$factory->define(Campaign::class, function (Faker $faker) {
    return [
        'agency_id' => Company::where('type', 'agency')->inRandomOrder()->first()->id,
        'dealership_id' => Company::where('type', 'dealership')->first()->id,
        'phone_number_id' => PhoneNumber::inRandomOrder()->first()->id,
        'name' => $faker->name,
        'order_id' => $faker->numberBetween(1, 10),
        'adf_crm_export' => $faker->randomElement([true, false]),
        'adf_crm_export_email' => $faker->safeEmail,
        'lead_alerts' => $faker->randomElement([true, false]),
        'lead_alert_email' => $faker->email,
        'client_passthrough' => $faker->randomElement([true, false]),
        'client_passthrough_email' => $faker->email,
        'starts_at' => Carbon::now()->toDateTimeString(),
        'ends_at' => Carbon::now()->toDateTimeString(),
        'status' => $faker->randomElement(['Active', 'Archived', 'Completed', 'Expired', 'Upcoming'])
    ];
});
