<?php
// /de/admin_ticket_view.php

$current_language = 'de';
require_once __DIR__ . '/../config/bootstrap.php';

if (!$is_logged_in) { redirect($current_language . '/login'); }
if (!$is_admin) {
    set_flash_message(lang('error_no_permission'), 'error');
    redirect($current_language . '/dashboard');
}

$current_script = 'admin_ticket_view';
$ticket_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$ticket_id) {
    set_flash_message(lang('error_invalid_id'), 'error');
    redirect($current_language . '/admin_tickets');
}

// Prüfen ob Ticket existiert
$stmt = $conn->prepare("SELECT t.*, u.username as creator_name FROM tickets t JOIN users u ON t.user_id = u.id WHERE t.id = ?");
$stmt->bind_param("i", $ticket_id);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ticket) {
    set_flash_message(lang('error_invalid_id'), 'error');
    redirect($current_language . '/admin_tickets');
}

$page_title = lang('title_ticket_view') . ' #' . $ticket['id'];

// --- POST Handling ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token();
    
    if (isset($_POST['reply_ticket'])) {
        $message = trim(filter_input(INPUT_POST, 'message', FILTER_UNSAFE_RAW));
        $new_status = isset($_POST['close_on_reply']) ? 'closed' : $ticket['status'];

        if (empty($message)) {
            set_flash_message(lang('error_all_fields_required'), 'error');
        } else {
            $stmt_msg = $conn->prepare("INSERT INTO ticket_messages (ticket_id, user_id, message) VALUES (?, ?, ?)");
            $stmt_msg->bind_param("iis", $ticket_id, $current_user_id, $message);
            if ($stmt_msg->execute()) {
                // Update ticket updated_at and potentially status
                $stmt_upd = $conn->prepare("UPDATE tickets SET updated_at = NOW(), status = ? WHERE id = ?");
                $stmt_upd->bind_param("si", $new_status, $ticket_id);
                $stmt_upd->execute();
                $stmt_upd->close();
                
                set_flash_message(lang('success_ticket_replied'), 'success');
                redirect($current_language . '/admin_ticket_view?id=' . $ticket_id);
            } else {
                set_flash_message(lang('error_db_insert'), 'error');
            }
            $stmt_msg->close();
        }
    } elseif (isset($_POST['change_status'])) {
        $new_status = $_POST['status'] === 'open' ? 'open' : 'closed';
        $stmt_close = $conn->prepare("UPDATE tickets SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt_close->bind_param("si", $new_status, $ticket_id);
        if ($stmt_close->execute()) {
            set_flash_message("Status geändert.", 'success');
            redirect($current_language . '/admin_ticket_view?id=' . $ticket_id);
        } else {
            set_flash_message(lang('error_db_update'), 'error');
        }
        $stmt_close->close();
    }
}

// Nachrichten laden
$messages = [];
$stmt_msgs = $conn->prepare("
    SELECT tm.*, u.username, u.role 
    FROM ticket_messages tm 
    JOIN users u ON tm.user_id = u.id 
    WHERE tm.ticket_id = ? 
    ORDER BY tm.created_at ASC
");
$stmt_msgs->bind_param("i", $ticket_id);
$stmt_msgs->execute();
$res_msgs = $stmt_msgs->get_result();
while ($row = $res_msgs->fetch_assoc()) {
    $messages[] = $row;
}
$stmt_msgs->close();

include __DIR__ . '/../includes/header.php';
?>

<style>
.ticket-message.admin-reply {
    border-left: 4px solid var(--button-bg, #007bff);
    background-color: var(--table-row-even, #f8f9fa);
}
.message-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 15px;
    font-size: 0.9em;
    color: var(--text-secondary, #666);
    border-bottom: 1px solid var(--table-border, #eee);
    padding-bottom: 10px;
}
.message-author {
    font-weight: bold;
    color: var(--text-color, #333);
}
.message-body {
    white-space: pre-wrap;
    line-height: 1.5;
    color: var(--text-color, #333);
}
.badge-admin {
    background-color: var(--button-bg, #007bff);
    color: var(--button-text, white);
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.8em;
    margin-left: 5px;
}
.ticket-metadata {
    display: flex;
    gap: 20px;
    align-items: center;
}
</style>

<div class="content-wrapper">
    <div class="view-header">
        <h1>
            Ticket #<?php echo $ticket['id']; ?>: <?php echo htmlspecialchars($ticket['subject']); ?> 
        </h1>
        <a href="<?php echo htmlspecialchars($base_path . '/' . $current_language); ?>/admin_tickets" class="button button-secondary">Zurück zur Übersicht</a>
    </div>

    <div class="ticket-metadata card">
        <div><strong>Von:</strong> <?php echo htmlspecialchars($ticket['creator_name']); ?></div>
        <div><strong>Status:</strong> 
            <span class="badge <?php echo $ticket['status'] === 'open' ? 'badge-success' : 'badge-secondary'; ?>">
                <?php echo $ticket['status'] === 'open' ? lang('status_open') : lang('status_closed'); ?>
            </span>
        </div>
        <div>
            <form method="post" action="" style="display:inline; margin:0;">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="status" value="<?php echo $ticket['status'] === 'open' ? 'closed' : 'open'; ?>">
                <button type="submit" name="change_status" class="button button-small button-secondary">
                    <?php echo $ticket['status'] === 'open' ? 'Ticket schließen' : 'Ticket wieder öffnen'; ?>
                </button>
            </form>
        </div>
    </div>

    <div class="ticket-messages-container">
        <?php foreach ($messages as $msg): ?>
            <div class="ticket-message card <?php echo ($msg['role'] === 'admin' || $msg['role'] === 'owner') ? 'admin-reply' : ''; ?>">
                <div class="message-header">
                    <span class="message-author">
                        <?php echo htmlspecialchars($msg['username']); ?>
                        <?php if ($msg['role'] === 'admin' || $msg['role'] === 'owner'): ?>
                            <span class="badge-admin">Admin</span>
                        <?php endif; ?>
                    </span>
                    <span class="message-date"><?php echo format_date_lang($msg['created_at']); ?></span>
                </div>
                <div class="message-body"><?php echo htmlspecialchars($msg['message']); ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card" style="margin-top: 30px;">
        <h3><?php echo lang('button_reply'); ?></h3>
        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <div class="form-group">
                <textarea name="message" rows="5" required placeholder="<?php echo lang('label_ticket_message'); ?>"></textarea>
            </div>
            <div class="form-group" style="display: flex; align-items: center; gap: 10px; justify-content: flex-start;">
                <input type="checkbox" id="close_on_reply" name="close_on_reply" <?php echo $ticket['status'] === 'closed' ? 'checked' : ''; ?> style="width: auto; margin: 0; display: inline-block;">
                <label for="close_on_reply" style="margin: 0; display: inline-block;">Ticket nach Antwort schließen</label>
            </div>
            <div class="form-actions">
                <button type="submit" name="reply_ticket" class="button"><?php echo lang('button_reply'); ?></button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
