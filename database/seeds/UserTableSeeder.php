<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class UserTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $user = new User();
        $user->first_name = 'Carlos';
        $user->last_name = 'Arauz';
        $user->email = 'carauzs@gmail.com';
        $user->username = 'carauzs';
        $user->is_admin = true;
        $user->email_verified_at = Carbon::now()->toDateTimeString();
        $user->timezone = 'US/Alaska';
        $user->password = bcrypt('password');
        $user->save();

        $faker = Faker\Factory::create();
        factory(User::class, 50)
            ->create()
            ->each(function ($user) use ($faker) {
               $company = Company::inRandomOrder()->first();
               $company->users()->save($user, [
                   'role'=> $faker->randomElement(['admin', 'user']),
               ]);
            });
    }
}
