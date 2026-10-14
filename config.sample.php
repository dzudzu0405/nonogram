<?php
// Copy this file to config.php and fill in your values.
// config.php is blocked from the web by .htaccess — never share it.
return [
    'app_name'  => 'Nonogram Studio',

    // The site's full address, no slash at the end, e.g. 'https://studio.yourdomain.com' (or '.../studio').
    // Access links and password-reset emails use it. Leave empty only while testing.
    'app_url'   => '',

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

    // Email for password resets and welcome messages. Create a mailbox in cPanel > Email Accounts
    // (e.g. noreply@yourdomain.com), then open "Connect Devices" on it for the host and port.
    // Typical cPanel: port 465 with 'ssl', or 587 with 'tls'. The username is the full address.
    // 'driver' => 'log' sends nothing and writes messages to storage/logs/mail.log (local testing with debug on).
    'mail' => [
        'driver'     => 'smtp',
        'host'       => '',            // e.g. mail.yourdomain.com
        'port'       => 465,
        'encryption' => 'ssl',
        'username'   => '',            // e.g. noreply@yourdomain.com
        'password'   => '',
        'from_email' => '',            // usually the same as username
        'from_name'  => 'Nonogram Studio',
        'reply_to'   => '',            // optional: where replies go, e.g. support@yourdomain.com
    ],

    // Sign in with Google (optional). Leave both empty to hide the Google button.
    //   1. console.cloud.google.com/apis/credentials > Create credentials > OAuth client ID > Web application
    //   2. Authorised redirect URI: your app_url + /google.php, e.g. https://studio.yourdomain.com/google.php
    //      (Google only accepts https://)
    //   3. Copy the client ID and secret here. The cURL PHP extension must be on.
    'google' => [
        'client_id'     => '',
        'client_secret' => '',
    ],

    // Sales pages for the upgrade buttons in the app (sidebar plan card and dashboard). Leave a product empty
    // to hide its button. Keys: pro, vault, dfy10, agency.
    'upgrade_urls' => [
        'pro'    => '',
        'vault'  => '',
        'dfy10'  => '',
        'agency' => '',
    ],

    // false: accounts can only be created from an access link (recommended for WarriorPlus sales).
    // true: anyone can sign up; without a product they see "no license yet".
    'open_registration' => false,

    // Show detailed errors. Keep false on the live site.
    'debug'     => false,
];
