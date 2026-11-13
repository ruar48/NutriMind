<?php

return [

    /*
    |--------------------------------------------------------------------------
    | System Settings Defaults
    |--------------------------------------------------------------------------
    |
    | Default values for the administrator-editable system settings. Values an
    | administrator saves are stored in the "settings" table and override the
    | defaults below; read them through App\Services\Settings. A null
    | "role_permissions" value means the Permission enum's default roles apply.
    |
    */

    'settings' => [
        'school_name' => 'NutriMind Elementary School',
        'school_id' => null,
        'school_address' => null,
        'school_head' => null,
        'school_email' => null,
        'school_phone' => null,
        'default_passing_score' => 75,
        'default_max_attempts' => 2,
        'default_learning_mode' => 'automatic',
        'points_per_level' => 500,
        'max_level' => 50,
        'forum_enabled' => true,
        'forum_requires_approval' => false,
        'announcement' => null,
        'role_permissions' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Upload Limits
    |--------------------------------------------------------------------------
    |
    | Maximum sizes, in kilobytes, of quiz images and videos. The video limit
    | is further capped by PHP's upload_max_filesize setting.
    |
    */

    'uploads' => [
        'image_max_kb' => 2048,
        'video_max_kb' => 20480,
    ],

    /*
    |--------------------------------------------------------------------------
    | Learner CSV Import
    |--------------------------------------------------------------------------
    |
    | Limits of the learner CSV import: the most rows one file may contain and
    | the largest file, in kilobytes, that may be uploaded.
    |
    */

    'import' => [
        'max_rows' => 500,
        'max_kb' => 2048,
    ],

];
