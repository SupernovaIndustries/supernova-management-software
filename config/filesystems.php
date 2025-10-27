<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],

        // Syncthing Integration Disks - Modular Configuration
        'syncthing_clients' => [
            'driver' => 'local',
            'root' => env('SYNCTHING_CLIENTS_PATH', storage_path('app/syncthing/clients')),
            'url' => env('APP_URL').'/syncthing/clients',
            'visibility' => 'private',
            'throw' => false,
        ],

        'syncthing_documents' => [
            'driver' => 'local',
            'root' => env('SYNCTHING_DOCUMENTS_PATH', storage_path('app/syncthing/documents')),
            'url' => env('APP_URL').'/syncthing/documents',
            'visibility' => 'private',
            'throw' => false,
        ],

        'syncthing_warehouse' => [
            'driver' => 'local',
            'root' => env('SYNCTHING_WAREHOUSE_PATH', storage_path('app/syncthing/warehouse')),
            'url' => env('APP_URL').'/syncthing/warehouse',
            'visibility' => 'private',
            'throw' => false,
        ],

        'syncthing_templates' => [
            'driver' => 'local',
            'root' => env('SYNCTHING_TEMPLATES_PATH', storage_path('app/syncthing/templates')),
            'url' => env('APP_URL').'/syncthing/templates',
            'visibility' => 'private',
            'throw' => false,
        ],

        'syncthing_prototypes' => [
            'driver' => 'local',
            'root' => env('SYNCTHING_PROTOTYPES_PATH', storage_path('app/syncthing/prototypes')),
            'url' => env('APP_URL').'/syncthing/prototypes',
            'visibility' => 'private',
            'throw' => false,
        ],

        'syncthing_deepsouth' => [
            'driver' => 'local',
            'root' => env('SYNCTHING_DEEPSOUTH_PATH', storage_path('app/syncthing/deepsouth')),
            'url' => env('APP_URL').'/syncthing/deepsouth',
            'visibility' => 'private',
            'throw' => false,
        ],

        'syncthing_finance' => [
            'driver' => 'local',
            'root' => env('SYNCTHING_FINANCE_PATH', storage_path('app/syncthing/finance')),
            'url' => env('APP_URL').'/syncthing/finance',
            'visibility' => 'private',
            'throw' => false,
        ],

        'syncthing_logos' => [
            'driver' => 'local',
            'root' => env('SYNCTHING_LOGOS_PATH', storage_path('app/syncthing/logos')),
            'url' => env('APP_URL').'/syncthing/logos',
            'visibility' => 'public',
            'throw' => false,
        ],

        'syncthing_marketing' => [
            'driver' => 'local',
            'root' => env('SYNCTHING_MARKETING_PATH', storage_path('app/syncthing/marketing')),
            'url' => env('APP_URL').'/syncthing/marketing',
            'visibility' => 'private',
            'throw' => false,
        ],

        'syncthing_partnership' => [
            'driver' => 'local',
            'root' => env('SYNCTHING_PARTNERSHIP_PATH', storage_path('app/syncthing/partnership')),
            'url' => env('APP_URL').'/syncthing/partnership',
            'visibility' => 'private',
            'throw' => false,
        ],

        'syncthing_planning' => [
            'driver' => 'local',
            'root' => env('SYNCTHING_PLANNING_PATH', storage_path('app/syncthing/planning')),
            'url' => env('APP_URL').'/syncthing/planning',
            'visibility' => 'private',
            'throw' => false,
        ],

        'syncthing_pitch' => [
            'driver' => 'local',
            'root' => env('SYNCTHING_PITCH_PATH', storage_path('app/syncthing/pitch')),
            'url' => env('APP_URL').'/syncthing/pitch',
            'visibility' => 'private',
            'throw' => false,
        ],

        'syncthing_shareit' => [
            'driver' => 'local',
            'root' => env('SYNCTHING_SHAREIT_PATH', storage_path('app/syncthing/shareit')),
            'url' => env('APP_URL').'/syncthing/shareit',
            'visibility' => 'private',
            'throw' => false,
        ],

        'syncthing_site' => [
            'driver' => 'local',
            'root' => env('SYNCTHING_SITE_PATH', storage_path('app/syncthing/site')),
            'url' => env('APP_URL').'/syncthing/site',
            'visibility' => 'public',
            'throw' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];