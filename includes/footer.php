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
                <a href="<?php echo $base_path; ?>/de/imprint"><?php echo lang('link_imprint'); ?></a> | <a href="<?php echo $base_path; ?>/de/privacy"><?php echo lang('link_privacy'); ?></a>
                <?php if (DEBUG_MODE && isset($conn) && $conn instanceof mysqli && @$conn->ping()): ?> | <span style="color: #28a745;">DB Ok</span><?php elseif (DEBUG_MODE): ?> | <span style="color: #dc3545;">DB Err</span><?php endif; ?>
            </small>
        </div>
    </footer>
    
    <!-- Rename Modal -->
    <div id="renameModal" class="modal-overlay" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3>Umbenennen</h3>
                <button type="button" class="modal-close" aria-label="Schließen" onclick="closeRenameModal()">×</button>
            </div>
            <div class="modal-content">
                <input type="text" id="renameModalInput" class="form-control" placeholder="Neuer Dateiname" style="width: 100%;" />
                <input type="hidden" id="renameModalFileId" />
            </div>
            <div class="modal-footer">
                <button type="button" class="button button-secondary modal-close" onclick="closeRenameModal()">Abbrechen</button>
                <button type="button" class="button button-primary" id="renameModalSubmit" onclick="submitRenameModal()">Speichern</button>
            </div>
        </div>
    </div>
    
    <!-- Cookie Banner -->
    <div id="cookie-banner" class="cookie-banner" aria-hidden="true" style="display: none;">
        <div class="cookie-content">
            <h4>Hinweis zu Cookies</h4>
            <p>Wir verwenden auf dieser Website ausschließlich technisch notwendige Cookies (z. B. für den Login und die Sicherheit). Es werden keine Tracking- oder Werbe-Cookies eingesetzt.</p>
            <div class="cookie-actions">
                <button type="button" class="button button-primary" id="cookie-accept">Verstanden</button>
            </div>
        </div>
    </div>
    
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