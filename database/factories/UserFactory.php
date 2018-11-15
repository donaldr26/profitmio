<?php

use Faker\Generator as Faker;
use Carbon\Carbon;
use App\Models\User;

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

$factory->define(User::class, function (Faker $faker) {
    return [
        'first_name' => $faker->firstName,
        'last_name' => $faker->lastName,
        'username' => $faker->userName,
        'email' => $faker->unique()->safeEmail,
        'is_admin'=> $faker->randomElement([true, false]),
        'timezone'=> $faker->randomElement(User::getPossibleTimezonesForUser()),
        'email_verified_at' => Carbon::now()->toDateTimeString(),
        'password' => bcrypt('password')
    ];
});
