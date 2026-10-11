<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google_sheets' => [
        'credentials_path' => env('GOOGLE_APPLICATION_CREDENTIALS'),
        'attendance' => [
            'spreadsheet_id' => env('GOOGLE_SHEETS_ATTENDANCE_SPREADSHEET_ID'),
            'range' => env('GOOGLE_SHEETS_ATTENDANCE_RANGE', "'CLASS Attendance'!B6:ZZ"),
            'activation_range' => env('GOOGLE_SHEETS_ATTENDANCE_ACTIVATION_RANGE', "'CLASS Attendance'!B5:ZZ5"),
            'subject_code' => env('GOOGLE_SHEETS_ATTENDANCE_SUBJECT_CODE', 'PHP'),
            'date_format' => env('GOOGLE_SHEETS_ATTENDANCE_DATE_FORMAT', 'd/m'),
            'year' => (int) env('GOOGLE_SHEETS_ATTENDANCE_YEAR', date('Y')),
        ],
        'roster' => [
            'spreadsheet_id' => env('GOOGLE_SHEETS_ROSTER_SPREADSHEET_ID'),
            'range' => env('GOOGLE_SHEETS_ROSTER_RANGE', 'Roster!A1:E'),
        ],
    ],

];
