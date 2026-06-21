<?php
// /de/admin_tickets.php

$current_language = 'de';
require_once __DIR__ . '/../config/bootstrap.php';

if (!$is_logged_in) { redirect($current_language . '/login'); }
if (!$is_admin) {
    set_flash_message(lang('error_no_permission'), 'error');
    redirect($current_language . '/dashboard');
}

$current_script = 'admin_tickets';
$page_title = lang('title_admin_tickets');

// --- Filter ---
$status_filter = isset($_GET['status']) && in_array($_GET['status'], ['open', 'closed']) ? $_GET['status'] : 'all';

// --- Daten laden ---
$tickets = [];
$query = "
    SELECT t.*, u.username, 
           (SELECT created_at FROM ticket_messages tm WHERE tm.ticket_id = t.id ORDER BY created_at DESC LIMIT 1) as last_update 
    FROM tickets t 
    JOIN users u ON t.user_id = u.id 
";

if ($status_filter !== 'all') {
    $query .= " WHERE t.status = ? ";
}

$query .= " ORDER BY t.status DESC, last_update DESC";

$stmt = $conn->prepare($query);
if ($status_filter !== 'all') {
    $stmt->bind_param("s", $status_filter);
}
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
        <h1><?php echo lang('title_admin_tickets'); ?></h1>
    </div>

    <div class="filters-container" style="margin-bottom: 20px;">
        <a href="?status=open" class="button <?php echo $status_filter === 'open' ? '' : 'button-secondary'; ?>">Offen</a>
        <a href="?status=closed" class="button <?php echo $status_filter === 'closed' ? '' : 'button-secondary'; ?>">Geschlossen</a>
        <a href="?status=all" class="button <?php echo $status_filter === 'all' ? '' : 'button-secondary'; ?>">Alle</a>

    </div>

    <?php if (empty($tickets)): ?>
        <p class="empty-state"><?php echo lang('text_no_tickets'); ?></p>
    <?php else: ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th><?php echo lang('th_ticket_id'); ?></th>
                        <th><?php echo lang('th_username'); ?></th>
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
                            <td><?php echo htmlspecialchars($ticket['username']); ?></td>
                            <td><?php echo htmlspecialchars($ticket['subject']); ?></td>
                            <td>
                                <span class="badge <?php echo $ticket['status'] === 'open' ? 'badge-success' : 'badge-secondary'; ?>">
                                    <?php echo $ticket['status'] === 'open' ? lang('status_open') : lang('status_closed'); ?>
                                </span>
                            </td>
                            <td><?php echo format_date_lang($ticket['last_update'] ?? $ticket['created_at']); ?></td>
                            <td class="actions-cell">
                                <a href="<?php echo htmlspecialchars($base_path . '/' . $current_language); ?>/admin_ticket_view?id=<?php echo $ticket['id']; ?>" class="button button-small"><?php echo lang('button_view'); ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
