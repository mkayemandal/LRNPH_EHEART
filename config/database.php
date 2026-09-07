<?php
// Application Configuration File - Production Database Settings

define('DB_SERVERS', [
    // Main Production DB (10.2.0.9)
    'main_db' => [
        'host'      => '10.2.0.9',
        'port'      => '1433',
        'database'  => 'LRNPH_HR', 
        'username'  => 'mmandal', 
        'password'  => 'Mk093003' 
    ],

    // Testing DB (10.2.0.167)
    // 'main_db' => [
    //     'host'      => '10.2.0.167',
    //     'port'      => '1433',
    //     'database'  => 'LRNPH_HR_TEST',
    //     'username'  => 'mmandal',
    //     'password'  => 'Mk093003'
    // ],

    'lrnph' => [
        'host'      => '10.2.0.9',
        'port'      => '1433',
        'database'  => 'LRNPH',
        'username'  => 'mmandal',
        'password'  => 'Mk093003'
    ],

    'lrnph_e' => [
        'host'      => '10.2.0.9',
        'port'      => '1433',
        'database'  => 'LRNPH_E',
        'username'  => 'mmandal',
        'password'  => 'Mk093003'
    ]
]);
