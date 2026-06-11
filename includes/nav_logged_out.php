<?php
// /includes/nav_logged_out.php
// Wird von header.php included. Nimmt an, dass $current_script, $current_language bekannt sind.

$lang_prefix = '/' . $current_language . '/'; // z.B. /de/
?>
<a href="<?php echo $lang_prefix; ?>login" class="<?php echo $current_script == 'login' ? 'active' : ''; ?>"><?php echo lang('nav_login'); ?></a>
<a href="<?php echo $lang_prefix; ?>register" class="button <?php echo $current_script == 'register' ? 'active' : ''; ?>"><?php echo lang('nav_register'); ?></a>