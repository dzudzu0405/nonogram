<?php
require __DIR__ . '/lib/bootstrap.php';
// POST with the CSRF token only, so another site cannot sign people out with a link or an <img>.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valid($_POST['csrf'] ?? null)) redirect(current_user() ? 'app/' : 'login.php');
logout();
start_session();
flash('success', 'You are signed out.');
redirect('login.php');
