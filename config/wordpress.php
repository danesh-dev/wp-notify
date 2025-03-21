<?php

// config/wordpress.php
return [
    // Your notification email
    'notification_email' => env('NOTIFICATION_EMAIL', 'danesh-dev07@example.com'),

    // Websites to monitor
    'websites' => [
        'instantgrabber' => 'https://instantgrabber.com/wp-json/wp/v2/posts',
        'saveautoglass' => 'https://saveautoglass.net/wp-json/wp/v2/posts',
    ],
];