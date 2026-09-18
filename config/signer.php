<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Signature stamp
    |--------------------------------------------------------------------------
    |
    | Default width of a placed signature in percent of the page width. The
    | height follows from the aspect ratio of the captured signature image.
    |
    */

    'default_signature_width' => 20.0,

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | The filesystem disk and base path (see `storage_path` below) used to
    | store original documents, captured signature images and the final
    | signed PDF files.
    |
    */

    'disk' => env('SIGNER_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Signing links
    |--------------------------------------------------------------------------
    |
    | Signing invitations use temporary signed URLs. This value controls how
    | many hours a signing link stays valid after it has been sent.
    |
    */

    'link_expires_after_hours' => env('SIGNER_LINK_EXPIRES_AFTER_HOURS', 72),

    /*
    |--------------------------------------------------------------------------
    | Portal
    |--------------------------------------------------------------------------
    |
    | The package ships a full admin portal with login, customers and document
    | management. Disable it when you only want the signing flow and the API.
    | The portal authenticates against the given guard, so any user of the
    | host application can log in.
    |
    */

    'portal' => [
        'enabled' => env('SIGNER_PORTAL_ENABLED', true),
        'guard' => 'web',
        'middleware' => ['web'],
        'prefix' => 'portal',
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | Prefix and middleware for the signing routes that the package registers.
    |
    */

    'route_middleware' => ['web'],

    'route_prefix' => 'sign',

    /*
    |--------------------------------------------------------------------------
    | Storage path
    |--------------------------------------------------------------------------
    |
    | Base path within the disk for originals, signatures and signed PDFs.
    |
    */

    'storage_path' => env('SIGNER_STORAGE_PATH', 'signer'),

];
