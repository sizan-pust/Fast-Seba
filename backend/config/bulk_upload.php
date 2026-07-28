<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Bulk upload storage
    |--------------------------------------------------------------------------
    |
    | HyperLocal keeps bulk-upload artifacts on an explicitly selected disk.
    | FastSheba defaults to Laravel's private local disk so CSV files are not
    | exposed through the public storage symlink.
    |
    */
    'file_disk' => env('BULK_UPLOAD_DISK', 'local'),
];
