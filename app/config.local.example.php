<?php
// Copy to config.local.php on the server. That file stays out of git.
// Make a password hash with:  php -r "echo password_hash('the password', PASSWORD_DEFAULT);"
return [
    'secret' => 'a long random string',
    'first_user' => [
        'username' => 'sabir',
        'name' => 'Sabir',
        'password_hash' => '$2y$10$...',
    ],
];
