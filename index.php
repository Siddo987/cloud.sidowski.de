<?php
// /de/index.php

// Bootstrap laden (Pfad korrigiert)
require_once __DIR__ . '/config/bootstrap.php';

// Sprache setzen (wird von bootstrap nicht mehr unbedingt benötigt, aber gut für Klarheit)
$current_language = 'de';

// Wenn eingeloggt, zum Dashboard, sonst zum Login
if (is_logged_in()) {
    redirect('de/dashboard'); // Pfad für redirect bleibt gleich
} else {
    redirect('de/login'); // Pfad für redirect bleibt gleich
}
?>