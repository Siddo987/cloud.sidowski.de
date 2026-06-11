<?php
// /includes/nav_logged_in.php
// Wird von /includes/header.php eingebunden. Benötigt $current_language, $current_script, $is_admin, $current_username.

// Globale Variablen explizit holen
global $current_language, $current_script, $is_admin, $current_username;
$lang_prefix = '/' . ($current_language ? $current_language . '/' : ''); // z.B. '/de/' oder '/'
$base_path = defined('BASE_URL') && BASE_URL ? rtrim(BASE_URL, '/') : ''; // Basis-URL holen

?>
<?php // -- Direkte Links -- ?>
<a href="<?php echo htmlspecialchars($base_path . $lang_prefix); ?>dashboard" class="<?php echo $current_script == 'dashboard' ? 'active' : ''; ?>"><?php echo lang('nav_dashboard'); ?></a>
<a href="<?php echo htmlspecialchars($base_path . $lang_prefix); ?>upload" class="button <?php echo $current_script == 'upload' ? 'active' : ''; ?>"><?php echo lang('button_upload'); ?></a>


<?php // --- Dateien Dropdown --- ?>
<div class="dropdown nav-item">
    <?php // Button zum Öffnen des Dropdowns (OHNE caret span) ?>
    <button type="button" class="dropdown-toggle" aria-haspopup="true" aria-expanded="false">
        <?php echo lang('nav_files'); ?>
    </button>
    <?php // Das eigentliche Dropdown-Menü (initial versteckt) ?>
    <div class="dropdown-menu">
        <a href="<?php echo htmlspecialchars($base_path . $lang_prefix); ?>own_files" class="<?php echo $current_script == 'own_files' ? 'active' : ''; ?> dropdown-item"><?php echo lang('nav_my_files'); ?></a>
        <a href="<?php echo htmlspecialchars($base_path . $lang_prefix); ?>own_deleted_files" class="<?php echo $current_script == 'own_deleted_files' ? 'active' : ''; ?> dropdown-item">Gelöschte Dateien</a>
        <a href="<?php echo htmlspecialchars($base_path . $lang_prefix); ?>public_files" class="<?php echo $current_script == 'public_files' ? 'active' : ''; ?> dropdown-item"><?php echo lang('nav_public_files'); ?></a>
        <?php // Nur für Admins/Owner anzeigen ?>
        <?php if ($is_admin): ?>
            <hr class="dropdown-divider">
            <a href="<?php echo htmlspecialchars($base_path . $lang_prefix); ?>all_files" class="<?php echo $current_script == 'all_files' ? 'active' : ''; ?> dropdown-item"><?php echo lang('nav_all_files'); ?></a>
            <a href="<?php echo htmlspecialchars($base_path . $lang_prefix); ?>all_deleted_files" class="<?php echo $current_script == 'all_deleted_files' ? 'active' : ''; ?> dropdown-item">Alle gelöschten Dateien</a>
        <?php endif; ?>
    </div>
</div>


<?php // --- Admin Dropdown (nur wenn Admin/Owner) --- ?>
<?php if ($is_admin): ?>
<div class="dropdown nav-item">
    <button type="button" class="dropdown-toggle" aria-haspopup="true" aria-expanded="false">
        <?php echo lang('nav_admin'); // Neuer String 'nav_admin' => 'Verwaltung' ?>
    </button>
    <div class="dropdown-menu">
         <a href="<?php echo htmlspecialchars($base_path . $lang_prefix); ?>all_users" class="<?php echo $current_script == 'all_users' ? 'active' : ''; ?> dropdown-item"><?php echo lang('nav_all_users'); ?></a>
         <?php // Weitere Admin-Links hier einfügen, z.B. Logs, System-Einstellungen... ?>
    </div>
</div>
<?php endif; ?>


<?php // --- Einstellungen & Profil Dropdown (via Initialen) --- ?>
<div class="dropdown nav-item">
    <?php // Button mit Initialen als Trigger (OHNE caret span) ?>
    <button type="button" class="dropdown-toggle profile-initials-button" aria-haspopup="true" aria-expanded="false" title="<?php echo lang('nav_settings'); ?>">
        <?php echo htmlspecialchars(get_initials($current_username)); ?>
    </button>
    <?php // Das Dropdown-Menü, rechtsbündig ?>
    <div class="dropdown-menu dropdown-menu-right">
        <?php // Profil-Link ?>
        <a href="<?php echo htmlspecialchars($base_path . $lang_prefix); ?>profil" class="<?php echo $current_script == 'profil' ? 'active' : ''; ?> dropdown-item"><?php echo lang('nav_profile'); ?></a>

        <?php // Exit Impersonation - falls aktiv ?>
        <?php if (isset($_SESSION['_impersonated_by_admin_id'])): ?>
            <hr class="dropdown-divider">
            <a href="<?php echo htmlspecialchars($base_path . $lang_prefix); ?>exit_impersonation" class="dropdown-item" style="color: #ffc107; font-weight: bold;">⚠️ Exit Impersonation</a>
        <?php endif; ?>

        <hr class="dropdown-divider">
        <?php // Theme-Auswahl ?>
        <div class="dropdown-item-text"><?php echo lang('theme_mode'); ?>:</div>
        <button type="button" class="dropdown-item theme-switch-button" data-theme="light"><?php echo lang('theme_light'); ?></button>
        <button type="button" class="dropdown-item theme-switch-button" data-theme="dark"><?php echo lang('theme_dark'); ?></button>
        <button type="button" class="dropdown-item theme-switch-button" data-theme="system"><?php echo lang('theme_system'); ?></button>

        <hr class="dropdown-divider">
        <?php // Logout-Formular ?>
        <form method="post" action="<?php echo htmlspecialchars($base_path . $lang_prefix); ?>logout" style="margin: 0;">
             <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
             <button type="submit" class="dropdown-item logout-item"><?php echo lang('button_logout'); ?></button>
        </form>
    </div>
</div>