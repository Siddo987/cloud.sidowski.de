<?php
// /de/ticket_view.php

$current_language = 'de';
require_once __DIR__ . '/../config/bootstrap.php';

if (!$is_logged_in) { redirect($current_language . '/login'); }

$current_script = 'ticket_view';
$ticket_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$ticket_id) {
    set_flash_message(lang('error_invalid_id'), 'error');
    redirect($current_language . '/tickets');
}

// Prüfen ob Ticket existiert und dem Benutzer gehört
$stmt = $conn->prepare("SELECT * FROM tickets WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $ticket_id, $current_user_id);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ticket) {
    set_flash_message(lang('error_invalid_id'), 'error');
    redirect($current_language . '/tickets');
}

$page_title = lang('title_ticket_view') . ' #' . $ticket['id'];

// --- POST Handling ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token();
    
    if (isset($_POST['reply_ticket']) && $ticket['status'] === 'open') {
        $message = trim(filter_input(INPUT_POST, 'message', FILTER_UNSAFE_RAW));
        if (empty($message)) {
            set_flash_message(lang('error_all_fields_required'), 'error');
        } else {
            $stmt_msg = $conn->prepare("INSERT INTO ticket_messages (ticket_id, user_id, message) VALUES (?, ?, ?)");
            $stmt_msg->bind_param("iis", $ticket_id, $current_user_id, $message);
            if ($stmt_msg->execute()) {
                // Update ticket updated_at
                $conn->query("UPDATE tickets SET updated_at = NOW() WHERE id = " . $ticket_id);
                set_flash_message(lang('success_ticket_replied'), 'success');
                redirect($current_language . '/ticket_view?id=' . $ticket_id);
            } else {
                set_flash_message(lang('error_db_insert'), 'error');
            }
            $stmt_msg->close();
        }
    } elseif (isset($_POST['close_ticket']) && $ticket['status'] === 'open') {
        $stmt_close = $conn->prepare("UPDATE tickets SET status = 'closed', updated_at = NOW() WHERE id = ?");
        $stmt_close->bind_param("i", $ticket_id);
        if ($stmt_close->execute()) {
            set_flash_message(lang('success_ticket_closed'), 'success');
            redirect($current_language . '/ticket_view?id=' . $ticket_id);
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
</style>

<div class="content-wrapper">
    <div class="view-header">
        <h1>
            <?php echo htmlspecialchars($ticket['subject']); ?> 
            <span class="badge <?php echo $ticket['status'] === 'open' ? 'badge-success' : 'badge-secondary'; ?>" style="font-size: 0.5em; vertical-align: middle;">
                <?php echo $ticket['status'] === 'open' ? lang('status_open') : lang('status_closed'); ?>
            </span>
        </h1>
        <a href="<?php echo htmlspecialchars($base_path . '/' . $current_language); ?>/tickets" class="button button-secondary"><?php echo lang('button_back_tickets'); ?></a>
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

    <?php if ($ticket['status'] === 'open'): ?>
        <div class="card" style="margin-top: 30px;">
            <h3><?php echo lang('button_reply'); ?></h3>
            <form method="post" action="">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <div class="form-group">
                    <textarea name="message" rows="5" required placeholder="<?php echo lang('label_ticket_message'); ?>"></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" name="reply_ticket" class="button"><?php echo lang('button_reply'); ?></button>
                    <button type="submit" name="close_ticket" class="button button-danger" onclick="return confirm('Möchten Sie das Ticket wirklich schließen?');"><?php echo lang('button_close_ticket'); ?></button>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            Dieses Ticket ist geschlossen.
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
