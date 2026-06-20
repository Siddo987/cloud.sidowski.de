<?php
// /de/privacy.php
$current_language = 'de';
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card auth-card" style="max-width: 800px; text-align: left;">
    <h1><?php echo lang('title_privacy') ?: 'Datenschutzerklärung'; ?></h1>
    
    <div style="margin-top: 20px;">
        <p><strong>Hinweis zur Haushaltsausnahme:</strong> Diese Anwendung wird ausschließlich für rein persönliche und familiäre Zwecke betrieben. Die Erhebung und Verarbeitung von Daten beschränkt sich auf den für den Betrieb der Anwendung zwingend erforderlichen, privaten Rahmen.</p>

        <h3>1. Datenschutz auf einen Blick</h3>
        <h4>Allgemeine Hinweise</h4>
        <p>Die folgenden Hinweise geben einen einfachen Überblick darüber, was mit Ihren personenbezogenen Daten passiert, wenn Sie diese Website besuchen.</p>

        <h3>2. Datenerfassung auf dieser Website</h3>
        <h4>Cookies</h4>
        <p>Unsere Internetseiten verwenden so genannte „Cookies“. Auf dieser Website werden ausschließlich technisch notwendige Cookies eingesetzt, die für den Betrieb und die Sicherheit der Website erforderlich sind (z. B. das Session-Cookie für den Login-Status). Diese Cookies speichern keine personenbezogenen Informationen und dienen nicht dem Tracking oder der Werbung.</p>

        <h4>Server-Log-Dateien</h4>
        <p>Der Provider der Seiten erhebt und speichert automatisch Informationen in so genannten Server-Log-Dateien, die Ihr Browser automatisch an uns übermittelt. Dies sind u.A.: Browsertyp und Browserversion, verwendetes Betriebssystem, Referrer URL, Hostname des zugreifenden Rechners, Uhrzeit der Serveranfrage, IP-Adresse.</p>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
