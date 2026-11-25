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

    /*
    |--------------------------------------------------------------------------
    | Unity Game Sync
    |--------------------------------------------------------------------------
    |
    | The shape of the Unity game's missions, used to check and score the
    | progress the game uploads: every mission has this many checkpoints of
    | this many questions, and each correct checkpoint answer is worth the
    | given points (the game's GameConfig.pointsPerCorrectAnswer). A mission's
    | final challenge is worth the mission's own "reward points".
    |
    */

    'game' => [
        'checkpoints_per_mission' => 3,
        'questions_per_checkpoint' => 5,
        'points_per_correct_answer' => 10,

        // The game's worlds and the subject codes each one teaches: the admin mission form uses them to suggest
        // a "Game mission ID" (G{grade}-{world}-M{number}) for the chosen subject and grade.
        'worlds' => [
            'ENG' => ['ENGLISH'],
            'SCI' => ['SCIENCE'],
            'PEH' => ['PE', 'HEALTH'],
        ],

        // Every mission in the game (Assets/Resources/Content/*.json): game mission ID => title.
        'missions' => [
            'G5-ENG-M1' => 'The Golden Lantern Adventure',
            'G5-ENG-M2' => 'The Chronicles of Wisdom',
            'G5-ENG-M3' => 'The Explorer’s Atlas',
            'G6-ENG-M1' => 'The Critical Reader Quest',
            'G6-ENG-M2' => 'The Persuasion Investigator',
            'G6-ENG-M3' => 'The Evidence-Based Communicator',
            'G5-SCI-M1' => 'The Missing Matter Museum',
            'G5-SCI-M2' => 'The Nature Rescue Expedition',
            'G5-SCI-M3' => 'The Power of Earth and Sky',
            'G6-SCI-M1' => 'The Materials Investigation',
            'G6-SCI-M2' => 'The Ecosystem Engineering Quest',
            'G6-SCI-M3' => 'The Sky and Safety Expedition',
            'G5-PEH-M1' => 'Strong Mind, Smart Moves',
            'G5-PEH-M2' => 'Growing Well, Playing Together',
            'G5-PEH-M3' => 'Safe Choices, Active Rhythms',
            'G6-PEH-M1' => 'Whole-Self Wellness Challenge',
            'G6-PEH-M2' => 'Healthy Decisions and Game Strategy',
            'G6-PEH-M3' => 'Healthy Community, Active Environment',
        ],
    ],

];
