<?php
// /de/imprint.php
$current_language = 'de';
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card auth-card" style="max-width: 800px; text-align: left;">
    <h1><?php echo lang('title_imprint') ?: 'Impressum'; ?></h1>
    
    <div style="margin-top: 20px;">
        <p><strong>Hinweis:</strong> Diese Website wird ausschließlich für rein private und familiäre Zwecke betrieben. Gemäß § 5 TMG besteht daher keine Impressumspflicht.</p>

        <h3>Kontakt</h3>
        <p>
            Telefon: <?php echo htmlspecialchars(CONTACT_PHONE); ?><br>
            E-Mail: <?php echo htmlspecialchars(CONTACT_EMAIL); ?>
        </p>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
