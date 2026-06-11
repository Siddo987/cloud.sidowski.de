<?php
// /includes/footer.php
$base_path = defined('BASE_URL') && BASE_URL ? rtrim(BASE_URL, '/') : '';
$js_path = $base_path . '/js/main.js'; // Absoluter Pfad vom Root
$js_file_path = dirname(__DIR__) . '/js/main.js';
?>
    </main> <?php // Schließt <main> ?>
    <footer>
        <div class="container">
            <small>&copy; <?php echo date("Y"); ?> <?php echo htmlspecialchars(APP_NAME); ?> |
                <a href="#"><?php echo lang('link_imprint'); ?></a> | <a href="#"><?php echo lang('link_privacy'); ?></a>
                <?php if (DEBUG_MODE && isset($conn) && $conn instanceof mysqli && @$conn->ping()): ?> | <span style="color: #28a745;">DB Ok</span><?php elseif (DEBUG_MODE): ?> | <span style="color: #dc3545;">DB Err</span><?php endif; ?>
            </small>
        </div>
    </footer>
    <script src="<?php echo $js_path; ?>?v=<?php echo file_exists($js_file_path) ? filemtime($js_file_path) : time(); ?>"></script>
    <?php
        // Flash-Message als Toast anzeigen, wenn vorhanden
        $flash_message_data = get_flash_message();
        if ($flash_message_data):
            $message_text = lang($flash_message_data['key'], ...$flash_message_data['args']);
            $message_type = htmlspecialchars($flash_message_data['type']);
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            showAjaxFlash(<?php echo json_encode($message_text); ?>, <?php echo json_encode($message_type); ?>);
        });
    </script>
    <?php endif; ?>
</body>
</html>