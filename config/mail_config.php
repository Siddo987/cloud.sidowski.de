<?php
// /config/mail_config.php

// SMTP Konfiguration (aus .env / Umgebung)
define('SMTP_HOST', getenv('SMTP_HOST') ?: '');
define('SMTP_USER', getenv('SMTP_USER') ?: '');
define('SMTP_PASS', getenv('SMTP_PASS') ?: '');
define('SMTP_FROM', getenv('SMTP_FROM') ?: '');
define('SMTP_NAME', getenv('SMTP_NAME') ?: '');
define('SMTP_PORT', getenv('SMTP_PORT') ? (int)getenv('SMTP_PORT') : 0);
define('SMTP_SECURE', getenv('SMTP_SECURE') ?: '');
