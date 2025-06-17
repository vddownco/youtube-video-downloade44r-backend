<?php

return [
    /*
    |--------------------------------------------------------------------------
    | YT-DLP Path Configuration
    |--------------------------------------------------------------------------
    |
    | This value is the path to the yt-dlp executable. You can set this in
    | your .env file using YT_DLP_PATH variable.
    |
    */
    'yt_dlp_path' => env('YT_DLP_PATH', 'yt-dlp'),

    /*
    |--------------------------------------------------------------------------
    | Download Settings
    |--------------------------------------------------------------------------
    */
    'max_file_size' => env('MAX_DOWNLOAD_SIZE', 500000000), // 500MB
    'allowed_formats' => env('ALLOWED_FORMATS', 'mp4,mp3,webm,m4a'),
    'cleanup_after_hours' => env('CLEANUP_AFTER_HOURS', 24),
    'download_timeout' => env('DOWNLOAD_TIMEOUT', 3600), // 1 hour
];