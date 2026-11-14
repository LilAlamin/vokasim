<?php

return [
    'api_key' => env('OPENAI_API_KEY', env('CODEX_GATEWAY_API_KEY')),
    'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
    'text_model' => env('OPENAI_MODEL', 'gpt-5.6-sol'),
    'reasoning_effort' => env('OPENAI_REASONING_EFFORT', 'low'),
    'transcription_model' => env('OPENAI_TRANSCRIPTION_MODEL', 'gpt-transcribe'),
    'realtime_model' => env('OPENAI_REALTIME_MODEL', 'gpt-realtime-2.1-mini'),
    'voice' => env('OPENAI_VOICE', 'marin'),
    'http_timeout' => 60,
    'daily_sessions' => (int) env('INTERVIEW_DAILY_SESSIONS', 10),
    'answer_seconds' => (int) env('INTERVIEW_ANSWER_SECONDS', 180),
    'max_retries' => 3,
    'media_retention_days' => (int) env('INTERVIEW_MEDIA_RETENTION_DAYS', 90),
    'positions' => [
        'Junior Software Developer', 'Network Technician', 'Junior CNC Operator',
        'Teknisi Otomasi Industri', 'Teknisi Maintenance', 'Quality Control Inspector',
        'Junior Accountant', 'Digital Marketing Associate', 'Sous Chef', 'Hospitality Associate',
    ],
];
