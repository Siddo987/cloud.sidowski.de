<?php
// /de/tickets.php

$current_language = 'de';
require_once __DIR__ . '/../config/bootstrap.php';

if (!$is_logged_in) { redirect($current_language . '/login'); }

$current_script = 'tickets';
$page_title = lang('title_tickets');

// --- POST Handling ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_ticket'])) {
    validate_csrf_token();
    
    $subject = trim(filter_input(INPUT_POST, 'subject', FILTER_UNSAFE_RAW));
    $message = trim(filter_input(INPUT_POST, 'message', FILTER_UNSAFE_RAW));
    
    if (empty($subject) || empty($message)) {
        set_flash_message(lang('error_all_fields_required'), 'error');
    } else {
        $stmt = $conn->prepare("INSERT INTO tickets (user_id, subject) VALUES (?, ?)");
        $stmt->bind_param("is", $current_user_id, $subject);
        
        if ($stmt->execute()) {
            $ticket_id = $stmt->insert_id;
            $stmt->close();
            
            $stmt_msg = $conn->prepare("INSERT INTO ticket_messages (ticket_id, user_id, message) VALUES (?, ?, ?)");
            $stmt_msg->bind_param("iis", $ticket_id, $current_user_id, $message);
            $stmt_msg->execute();
            $stmt_msg->close();
            
            set_flash_message(lang('success_ticket_created'), 'success');
            redirect($current_language . '/ticket_view?id=' . $ticket_id);
        } else {
            set_flash_message(lang('error_db_insert'), 'error');
            $stmt->close();
        }
    }
}

// --- Daten laden ---
$tickets = [];
$stmt = $conn->prepare("SELECT t.*, (SELECT created_at FROM ticket_messages tm WHERE tm.ticket_id = t.id ORDER BY created_at DESC LIMIT 1) as last_update FROM tickets t WHERE t.user_id = ? ORDER BY last_update DESC");
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $tickets[] = $row;
}
$stmt->close();

include __DIR__ . '/../includes/header.php';
?>

<div class="content-wrapper">
    <div class="view-header">
        <h1><?php echo lang('title_tickets'); ?></h1>
        <button type="button" class="button" onclick="document.getElementById('createTicketForm').style.display = 'block';"><?php echo lang('button_create_ticket'); ?></button>
    </div>

    <div id="createTicketForm" class="form-container" style="display: none; margin-bottom: 20px;">
        <h3><?php echo lang('button_create_ticket'); ?></h3>
        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <div class="form-group">
                <label for="subject"><?php echo lang('label_ticket_subject'); ?></label>
                <input type="text" id="subject" name="subject" required>
            </div>
            <div class="form-group">
                <label for="message"><?php echo lang('label_ticket_message'); ?></label>
                <textarea id="message" name="message" rows="5" required></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" name="create_ticket" class="button"><?php echo lang('button_submit'); ?></button>
                <button type="button" class="button button-danger" onclick="document.getElementById('createTicketForm').style.display = 'none';">Abbrechen</button>
            </div>
        </form>
    </div>

    <?php if (empty($tickets)): ?>
        <p class="empty-state"><?php echo lang('text_no_tickets'); ?></p>
    <?php else: ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th><?php echo lang('th_ticket_id'); ?></th>
                        <th><?php echo lang('th_subject'); ?></th>
                        <th><?php echo lang('th_status'); ?></th>
                        <th><?php echo lang('th_last_update'); ?></th>
                        <th><?php echo lang('th_actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $ticket): ?>
                        <tr>
                            <td>#<?php echo $ticket['id']; ?></td>
                            <td><?php echo htmlspecialchars($ticket['subject']); ?></td>
                            <td>
                                <span class="badge <?php echo $ticket['status'] === 'open' ? 'badge-success' : 'badge-secondary'; ?>">
                                    <?php echo $ticket['status'] === 'open' ? lang('status_open') : lang('status_closed'); ?>
                                </span>
                            </td>
                            <td><?php echo format_date_lang($ticket['last_update'] ?? $ticket['created_at']); ?></td>
                            <td class="actions-cell">
                                <a href="<?php echo htmlspecialchars($base_path . '/' . $current_language); ?>/ticket_view?id=<?php echo $ticket['id']; ?>" class="button button-small"><?php echo lang('button_view'); ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
