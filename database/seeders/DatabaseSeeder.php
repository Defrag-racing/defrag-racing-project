<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        \App\Models\User::factory(500)->create([
            'id'             =>     rand(1, 500),
            'name'          =>      $this->faker->name,
            'email'         =>      $this->faker->unique()->safeEmail,
            'password'      =>      $this->faker->password,
            'username'      =>      $this->faker->userName,
            'country'       =>      $this->faker->country,
            'admin'         =>      0,
            'oldhash'       =>      null,
            'mdd_id'        =>      null,
            'twitter_name'  =>      $this->faker->userName,
            'twitch_name'   =>      $this->faker->userName,
            'discord_name'  =>      $this->faker->userName,
            'model'         =>      'sarge',
            'plain_name'    =>      $this->faker->name,
            'notification_settings' =>  'all'
        ]);
    }
}
