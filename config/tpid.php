<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Konfigurasi Spesifik untuk Analisis TPID
    |--------------------------------------------------------------------------
    */

    'locations' => [
        // Koordinat untuk analisis level kabupaten
        'mempawah' => [
            'name' => 'Kabupaten Mempawah',
            'latitude' => 0.36,
            'longitude' => 108.96,
        ],

        // Titik-titik sampel untuk analisis level provinsi
        'kalbar_regional' => [
            'singkawang' => [
                'name' => 'Singkawang',
                'latitude' => 0.91,
                'longitude' => 108.98,
            ],
            'sambas' => [
                'name' => 'Sambas',
                'latitude' => 1.35,
                'longitude' => 109.30,
            ],
            'pontianak' => [
                'name' => 'Pontianak',
                'latitude' => -0.02,
                'longitude' => 109.34,
            ],
            'sanggau' => [
                'name' => 'Sanggau',
                'latitude' => 0.12,
                'longitude' => 110.58,
            ]
        ],
    ],
];
