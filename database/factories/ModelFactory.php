<?php

$factory->define(App\Models\Mship\Account::class, function ($faker) {
    return [
        'id'           => rand(900000, 1300000),
        'name_first'   => $faker->name,
        'name_last'    => $faker->name,
        'email'        => $faker->email,
        'is_invisible' => 0,
    ];
});
$factory->define(App\Models\Mship\Role::class, function ($faker) {
    return [
        "name"               => $faker->word,
        "session_timeout"    => $faker->numberBetween(100, 1000),
        "password_mandatory" => false,
        "password_lifetime"  => 0,
    ];
});