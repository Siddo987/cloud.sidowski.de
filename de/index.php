<?php
// /de/index.php

// Bootstrap laden für Session-Handling etc.
require_once __DIR__ . '/../config/bootstrap.php';

// Wenn eingeloggt, zum Dashboard, sonst zum Login
if (is_logged_in()) {
    redirect('de/dashboard');
} else {
    redirect('de/login');
}
?>