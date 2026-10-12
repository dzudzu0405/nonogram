<?php
require __DIR__ . '/lib/bootstrap.php';
redirect(current_user() ? 'app/' : 'login.php');
