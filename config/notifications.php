<?php

return [
    'email_enabled' => (bool) env('VOKASIM_NOTIFICATION_EMAIL', false),

    'reminders' => [
        'assignment_hours' => 24,
        'exam_hours' => 48,
        'agreement_days' => 30,
    ],
];
