<?php
// /de/admin_debug.php
$current_language = 'de';
require_once __DIR__ . '/../config/bootstrap.php';

if (!$is_logged_in || !$is_admin) {
    set_flash_message('error_no_permission_view_file', 'error');
    redirect($current_language . '/dashboard');
}

// 1. Hole alle legitimen Pfade aus der Datenbank
$valid_paths = [];
$stmt = $conn->query("SELECT id, filename, physical_path, uploader_id FROM files");
while ($row = $stmt->fetch_assoc()) {
    $user_dir = rtrim(USER_UPLOAD_DIR, '/') . '/' . $row['uploader_id'];
    if (!empty($row['physical_path'])) {
        $real = realpath($user_dir . '/' . $row['physical_path']);
        if ($real) $valid_paths[] = $real;
    } else {
        $real1 = realpath($user_dir . '/' . $row['id'] . '_' . basename($row['filename']));
        $real2 = realpath($user_dir . '/' . basename($row['filename']));
        if ($real1) $valid_paths[] = $real1;
        if ($real2) $valid_paths[] = $real2;
    }
}
$valid_paths = array_filter($valid_paths);
$valid_paths = array_unique($valid_paths);

// 1.5 Hole alle User für das Zuweisungs-Dropdown
$all_users = [];
$res = $conn->query("SELECT id, username FROM users ORDER BY username ASC");
while ($u = $res->fetch_assoc()) {
    $all_users[] = $u;
}

// 2. Durchsuche das uploads/ Verzeichnis nach Dateien
$orphans = [];
$upload_dir = realpath(USER_UPLOAD_DIR);

if ($upload_dir && is_dir($upload_dir)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($upload_dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $path = $file->getRealPath();
            $filename = $file->getFilename();
            // Systemdateien ignorieren
            if ($filename === '.htaccess' || $filename === 'index.php' || $filename === 'index.html' || $filename === '.DS_Store') {
                continue;
            }
            // Wenn der Pfad NICHT in valid_paths existiert
            if (!in_array($path, $valid_paths)) {
                $orphans[] = [
                    'path' => $path,
                    'size' => $file->getSize(),
                    'mtime' => $file->getMTime()
                ];
            }
        }
    }
}

function formatBytes($bytes, $precision = 2) { 
    $units = array('B', 'KB', 'MB', 'GB', 'TB'); 
    $bytes = max($bytes, 0); 
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
    $pow = min($pow, count($units) - 1); 
    $bytes /= pow(1024, $pow); 
    return round($bytes, $precision) . ' ' . $units[$pow]; 
}

require_once __DIR__ . '/../includes/header.php';
?>

<main class="container">
    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--card-border); padding-bottom:15px; margin-bottom:20px;">
            <h2 style="margin:0;"><i class="icon-warning" style="color:var(--primary-color);"></i> Admin Debug Dashboard: Dateileichen</h2>
            <button class="button button-danger" onclick="deleteAllOrphans()">Alle Löschen</button>
        </div>

        <p>Hier werden physische Dateien im `uploads/` Verzeichnis aufgelistet, die keinen zugehörigen Eintrag mehr in der Datenbank haben. <br><strong>Achtung: Dies können auch unvollständige Uploads sein.</strong></p>

        <?php if (empty($orphans)): ?>
            <div class="empty-state">
                <i class="icon-check" style="font-size: 3rem; color: var(--success-color);"></i>
                <p>Der Server ist sauber. Keine Dateileichen gefunden.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Dateipfad (relativ)</th>
                            <th>Größe</th>
                            <th>Zuletzt geändert</th>
                            <th style="width: 100px;">Aktion</th>
                        </tr>
                    </thead>
                    <tbody id="orphan_table_body">
                        <?php foreach ($orphans as $orphan): ?>
                            <?php 
                                $rel_path = str_replace($upload_dir . DIRECTORY_SEPARATOR, '', $orphan['path']); 
                                $rel_path = str_replace('\\', '/', $rel_path);
                            ?>
                            <tr id="row_<?php echo md5($orphan['path']); ?>">
                                <td><?php echo htmlspecialchars($rel_path); ?></td>
                                <td><?php echo formatBytes($orphan['size']); ?></td>
                                <td><?php echo date('d.m.Y H:i', $orphan['mtime']); ?></td>
                                <td style="white-space: nowrap;">
                                    <button class="action-button" onclick="openRestoreModal('<?php echo htmlspecialchars(addslashes($orphan['path'])); ?>', '<?php echo htmlspecialchars(addslashes(basename($orphan['path']))); ?>', '<?php echo md5($orphan['path']); ?>')" title="Wiederherstellen/Zuweisen">
                                        <i class="icon-edit"></i>
                                    </button>
                                    <button class="action-button delete-button" onclick="deleteOrphan('<?php echo htmlspecialchars(addslashes($orphan['path'])); ?>', '<?php echo md5($orphan['path']); ?>')" title="Löschen">
                                        <i class="icon-delete"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Restore Modal -->
<div id="restoreModal" class="modal-overlay" aria-hidden="true" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div class="modal-dialog" style="background: var(--card-bg); padding: 20px; border-radius: 8px; width: 400px; max-width: 90%;">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h3 style="margin: 0;">Datei Zuweisen</h3>
            <button type="button" class="modal-close" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;" onclick="closeRestoreModal()">×</button>
        </div>
        <div class="modal-content">
            <input type="hidden" id="restoreModalPath" />
            <input type="hidden" id="restoreModalRowId" />
            
            <label style="display: block; margin-bottom: 5px;">Dateiname:</label>
            <input type="text" id="restoreModalFilename" class="form-control" style="width: 100%; margin-bottom: 15px;">
            
            <label style="display: block; margin-bottom: 5px;">Eigentümer:</label>
            <select id="restoreModalUploader" class="form-control" style="width: 100%; margin-bottom: 15px;">
                <?php foreach ($all_users as $u): ?>
                    <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['username']); ?></option>
                <?php endforeach; ?>
            </select>
            
            <label style="display: block; margin-bottom: 5px;">Sichtbarkeit:</label>
            <select id="restoreModalPublic" class="form-control" style="width: 100%; margin-bottom: 15px;">
                <option value="0">Privat</option>
                <option value="1">Öffentlich</option>
            </select>
        </div>
        <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 15px;">
            <button type="button" class="button button-secondary" onclick="closeRestoreModal()">Abbrechen</button>
            <button type="button" class="button button-primary" onclick="submitRestoreModal()">Zuweisen</button>
        </div>
    </div>
</div>

<script>
const csrfToken = "<?php echo csrf_token(); ?>";

function deleteOrphan(path, rowId) {
    if (!confirm('Soll diese Dateileiche wirklich gelöscht werden?')) return;
    
    let formData = new URLSearchParams();
    formData.append('path', path);
    formData.append('csrf_token', csrfToken);
    
    fetch('ajax_delete_orphan.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData.toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('row_' + rowId).remove();
            showAjaxFlash('Datei erfolgreich gelöscht', 'success');
        } else {
            showAjaxFlash('Fehler: ' + data.message, 'error');
        }
    })
    .catch(err => showAjaxFlash('Netzwerkfehler: ' + err.message, 'error'));
}

function deleteAllOrphans() {
    if (!confirm('Möchtest du WIRKLICH ALLE hier aufgelisteten Dateileichen unwiderruflich löschen?')) return;
    
    let formData = new URLSearchParams();
    formData.append('delete_all', '1');
    formData.append('csrf_token', csrfToken);
    
    fetch('ajax_delete_orphan.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData.toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('orphan_table_body').innerHTML = '';
            showAjaxFlash(data.deleted_count + ' Dateien erfolgreich gelöscht', 'success');
        } else {
            showAjaxFlash('Fehler: ' + data.message, 'error');
        }
    })
    .catch(err => showAjaxFlash('Netzwerkfehler: ' + err.message, 'error'));
}

function openRestoreModal(path, defaultName, rowId) {
    document.getElementById('restoreModalPath').value = path;
    document.getElementById('restoreModalRowId').value = rowId;
    document.getElementById('restoreModalFilename').value = defaultName;
    
    let modal = document.getElementById('restoreModal');
    modal.style.display = 'flex';
    modal.setAttribute('aria-hidden', 'false');
}

function closeRestoreModal() {
    let modal = document.getElementById('restoreModal');
    modal.style.display = 'none';
    modal.setAttribute('aria-hidden', 'true');
}

function submitRestoreModal() {
    let path = document.getElementById('restoreModalPath').value;
    let rowId = document.getElementById('restoreModalRowId').value;
    let filename = document.getElementById('restoreModalFilename').value;
    let uploaderId = document.getElementById('restoreModalUploader').value;
    let isPublic = document.getElementById('restoreModalPublic').value;
    
    if (!filename || !uploaderId) return;
    
    let formData = new URLSearchParams();
    formData.append('path', path);
    formData.append('filename', filename);
    formData.append('uploader_id', uploaderId);
    formData.append('public', isPublic);
    formData.append('csrf_token', csrfToken);
    
    fetch('ajax_restore_orphan.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData.toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('row_' + rowId).remove();
            closeRestoreModal();
            showAjaxFlash('Datei erfolgreich zugewiesen!', 'success');
        } else {
            showAjaxFlash('Fehler: ' + data.message, 'error');
        }
    })
    .catch(err => showAjaxFlash('Netzwerkfehler: ' + err.message, 'error'));
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
