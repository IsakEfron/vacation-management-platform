<?php

return [

    'defaults' => [
        'guard'     => 'empleado',   // Guard por defecto para toda la app
        'passwords' => 'empleados',
    ],

    'guards' => [
        'web' => [
            'driver'   => 'session',
            'provider' => 'users',
        ],

        // Guard personalizado para empleados de Canel's
        'empleado' => [
            'driver'   => 'session',
            'provider' => 'empleados',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model'  => App\Models\User::class,
        ],

        // Provider que apunta al modelo Empleado y usa 'nomina' como username
        'empleados' => [
            'driver' => 'eloquent',
            'model'  => App\Models\Empleado::class,
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table'    => 'password_reset_tokens',
            'expire'   => 60,
            'throttle' => 60,
        ],

        'empleados' => [
            'provider' => 'empleados',
            'table'    => 'password_reset_tokens',
            'expire'   => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 10800,

];