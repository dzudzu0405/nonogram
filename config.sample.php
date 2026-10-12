<?php
// Copy this file to config.php and fill in your values.
// config.php is blocked from the web by .htaccess — never share it.
return [
    'app_name'  => 'Nonogram Studio',

    // Leave empty to auto-detect. Set it if the app lives in a sub-folder, e.g. '/studio'.
    'base_path' => '',

    // MySQL on cPanel: create the database and user in "MySQL Databases",
    // then add the user to the database with ALL PRIVILEGES.
    'db' => [
        'driver'  => 'mysql',
        'host'    => 'localhost',
        'name'    => 'cpaneluser_nonogram',
        'user'    => 'cpaneluser_nonogram',
        'pass'    => 'change-me',
        'charset' => 'utf8mb4',
    ],
    // Local testing without MySQL:
    // 'db' => ['driver' => 'sqlite', 'path' => __DIR__ . '/storage/dev.sqlite'],

    // A long random string. Used to sign tokens. Changing it logs everyone out of magic links.
    'app_key'   => 'replace-with-64-random-characters',

    // Sender for welcome and login emails (create this mailbox in cPanel > Email Accounts).
    'mail_from' => 'support@example.com',

    // WarriorPlus notification security key (set in Phase 3).
    'wplus_key' => '',

    // Show detailed errors. Keep false on the live site.
    'debug'     => false,
];
