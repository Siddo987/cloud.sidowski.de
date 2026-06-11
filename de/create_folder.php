<?php
// /de/create_folder.php

$current_language = 'de';
require_once __DIR__ . '/../config/bootstrap.php';

if (!$is_logged_in) {
    redirect($current_language . '/login');
}

// Hole den aktuellen Ordner aus GET (optional)
$current_folder_id = isset($_GET['folder']) ? (int)$_GET['folder'] : null;

// Prüfe, ob der Ordner dem Benutzer gehört
if ($current_folder_id) {
    $stmt = $conn->prepare("SELECT id FROM folders WHERE id = ? AND user_id = ? AND deleted = 0");
    if (!$stmt) {
        set_flash_message('Datenbankfehler: Ordner-Tabelle nicht verfügbar', 'error');
        redirect($current_language . '/own_files');
    }
    $stmt->bind_param("ii", $current_folder_id, $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 0) {
        set_flash_message('Ordner nicht gefunden oder keine Berechtigung', 'error');
        redirect($current_language . '/own_files');
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token();

    $folder_name = trim($_POST['folder_name']);

    if (empty($folder_name)) {
        set_flash_message('Ordnername darf nicht leer sein', 'error');
    } elseif (!preg_match('/^[a-zA-Z0-9._\-\s\(\)]+$/', $folder_name) || strlen($folder_name) > 255) {
        set_flash_message('Ungültiger Ordnername', 'error');
    } else {
        // Prüfe, ob Ordner bereits existiert
        if ($current_folder_id === null) {
            $stmt = $conn->prepare("SELECT id FROM folders WHERE name = ? AND parent_id IS NULL AND user_id = ? AND deleted = 0");
            $bind_params = "si";
            $bind_values = [$folder_name, $current_user_id];
        } else {
            $stmt = $conn->prepare("SELECT id FROM folders WHERE name = ? AND parent_id = ? AND user_id = ? AND deleted = 0");
            $bind_params = "sii";
            $bind_values = [$folder_name, $current_folder_id, $current_user_id];
        }
        if (!$stmt) {
            set_flash_message('Datenbankfehler: Ordner-Tabelle nicht verfügbar', 'error');
        } else {
            $stmt->bind_param($bind_params, ...$bind_values);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                set_flash_message('Ein Ordner mit diesem Namen existiert bereits', 'error');
            } else {
                // Erstelle Ordner
                if ($current_folder_id === null) {
                    $stmt2 = $conn->prepare("INSERT INTO folders (name, parent_id, user_id) VALUES (?, NULL, ?)");
                    $bind_params2 = "si";
                    $bind_values2 = [$folder_name, $current_user_id];
                } else {
                    $stmt2 = $conn->prepare("INSERT INTO folders (name, parent_id, user_id) VALUES (?, ?, ?)");
                    $bind_params2 = "sii";
                    $bind_values2 = [$folder_name, $current_folder_id, $current_user_id];
                }
                if (!$stmt2) {
                    set_flash_message('Datenbankfehler: Ordner-Tabelle nicht verfügbar', 'error');
                } else {
                    $stmt2->bind_param($bind_params2, ...$bind_values2);
                    if ($stmt2->execute()) {
                        set_flash_message('Ordner erfolgreich erstellt', 'success');
                        redirect($current_language . '/own_files' . ($current_folder_id ? '?folder=' . $current_folder_id : ''));
                    } else {
                        set_flash_message('Fehler beim Erstellen des Ordners', 'error');
                    }
                    $stmt2->close();
                }
            }
            $stmt->close();
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<h1><i class="icon-folder"></i> Neuen Ordner erstellen</h1>
<div class="card create-folder-card">
    <div class="card-header">
        <h2>Ordnerdetails</h2>
    </div>
    <div class="card-body">
        <form method="post" action="" class="create-folder-form">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <div class="form-group">
                <label for="folder_name"><i class="icon-folder-open"></i> Ordnername:</label>
                <input type="text" id="folder_name" name="folder_name" required maxlength="255" pattern="[a-zA-Z0-9._\-\s\(\)]+" placeholder="z.B. Dokumente" autocomplete="off">
                <small class="form-help">Erlaubte Zeichen: Buchstaben, Zahlen, Leerzeichen, Punkte, Bindestriche, Unterstriche, Klammern</small>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-large">
                    <i class="icon-plus"></i> Ordner erstellen
                </button>
                <a href="<?php echo htmlspecialchars($base_path . $lang_prefix . 'own_files' . ($current_folder_id ? '?folder=' . $current_folder_id : '')); ?>" class="btn btn-secondary btn-large">
                    <i class="icon-cancel"></i> Abbrechen
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>