<?php
session_start();
include '../includes/db.php';
include '../includes/functions.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

function flash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

// ---- CSRF token (generate once per session) ----
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

/* =========================================================================
   Delete (single) / Bulk delete
   ========================================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrf_token, $submittedToken)) {
        flash('error', 'Your session expired or the form was resubmitted. Please try again.');
        redirect('messages.php');
    }

    try {
        if (isset($_POST['delete_single'])) {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                flash('error', 'Invalid message id.');
                redirect('messages.php');
            }
            $stmt = $pdo->prepare("DELETE FROM contact_messages WHERE id = ?");
            $stmt->execute([$id]);
            flash($stmt->rowCount() > 0 ? 'success' : 'error',
                  $stmt->rowCount() > 0 ? 'Message deleted.' : 'That message no longer exists.');
            redirect('messages.php');

        } elseif (isset($_POST['bulk_action']) && $_POST['bulk_action'] === 'delete') {
            $ids = array_values(array_unique(array_filter(array_map('intval', $_POST['selected'] ?? []))));
            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt = $pdo->prepare("DELETE FROM contact_messages WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                $deleted = $stmt->rowCount();
                flash('success', $deleted . ' message' . ($deleted === 1 ? '' : 's') . ' deleted.');
            } else {
                flash('error', 'No messages were selected.');
            }
            redirect('messages.php');
        }
    } catch (PDOException $e) {
        error_log('messages.php delete failed: ' . $e->getMessage());
        flash('error', 'Something went wrong while deleting. Please try again.');
        redirect('messages.php');
    }
}

/* =========================================================================
   DATA LAYER
   ========================================================================= */
$messages = [];
$dbError = null;
try {
    // Left-join offers so we can show which package (if any) each inquiry was about.
    $messages = $pdo->query("
        SELECT cm.*, o.title AS offer_title
        FROM contact_messages cm
        LEFT JOIN offers o ON cm.offer_id = o.id
        ORDER BY cm.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('messages.php fetch failed: ' . $e->getMessage());
    $dbError = 'Could not load messages right now. Please refresh the page.';
}

$totalMessages = count($messages);

$now = time();
$weekCount = 0;
$monthCount = 0;
$uniqueSenders = [];
foreach ($messages as $m) {
    $ts = strtotime($m['created_at']);
    if ($ts === false) continue;
    if ($ts >= $now - 7 * 86400) $weekCount++;
    if (date('Y-m', $ts) === date('Y-m')) $monthCount++;
    $uniqueSenders[strtolower(trim($m['email']))] = true;
}
$uniqueSenderCount = count($uniqueSenders);

// One-time flash message
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Page-specific setup for the shared header
$pageTitle = 'Contact Messages';
$extraHead = <<<HTML
<link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.bootstrap5.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/3.0.3/css/responsive.bootstrap5.css">
<style>
    .badge-new { background: #e63946; font-size: 0.7rem; padding: 0.4em 0.8em; }
    tr.is-new-msg { background: rgba(16,182,215,0.05); }
    .time-pill { cursor: pointer; border: none; }
    .time-pill.active { background: var(--primary-light) !important; color: #fff !important; }
    .copy-email-btn, .copy-phone-btn { cursor: pointer; }
    #modalMessage { white-space: pre-wrap; }
    .badge-package { background: rgba(137,217,0,0.15); color: #5c9200; font-weight: 600; }
    #replyWhatsApp.disabled, #replyEmail.disabled { pointer-events: none; opacity: 0.5; }
</style>
HTML;

include 'admin_header.php';
?>

        <!-- Header + Export -->
        <div class="row mb-4 align-items-center">
            <div class="col">
                <h2 class="fw-bold display-6" style="color:#033754;">Contact Messages</h2>
                <p class="text-muted lead mb-0">Manage all inquiries from your website.</p>
            </div>
            <div class="col-auto">
                <button id="exportCsvBtn" class="btn btn-outline-primary rounded-pill"><i class="fas fa-download me-2"></i>Export CSV</button>
            </div>
        </div>

        <!-- Flash message -->
        <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert" data-aos="fade-in">
                <i class="fas <?php echo $flash['type'] === 'success' ? 'fa-check-circle' : 'fa-triangle-exclamation'; ?> me-2"></i>
                <?php echo htmlspecialchars($flash['message'], ENT_QUOTES); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($dbError): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($dbError, ENT_QUOTES); ?></div>
        <?php endif; ?>

        <!-- Mini stats -->
        <div class="row g-4 mb-4">
            <div class="col-6 col-lg-3" data-aos="fade-up">
                <div class="mini-stat">
                    <div class="mini-icon" style="background:rgba(3,55,84,0.08); color:var(--primary-dark);"><i class="fas fa-envelope"></i></div>
                    <div>
                        <h4 class="fw-bold mb-0"><?php echo $totalMessages; ?></h4>
                        <small class="text-muted">Total Messages</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3" data-aos="fade-up" data-aos-delay="100">
                <div class="mini-stat">
                    <div class="mini-icon" style="background:rgba(255,77,79,0.12); color:#c0392b;"><i class="fas fa-bolt"></i></div>
                    <div>
                        <h4 class="fw-bold mb-0"><?php echo $weekCount; ?></h4>
                        <small class="text-muted">This Week</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3" data-aos="fade-up" data-aos-delay="200">
                <div class="mini-stat">
                    <div class="mini-icon" style="background:rgba(16,182,215,0.12); color:var(--primary-light);"><i class="fas fa-calendar-alt"></i></div>
                    <div>
                        <h4 class="fw-bold mb-0"><?php echo $monthCount; ?></h4>
                        <small class="text-muted">This Month</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3" data-aos="fade-up" data-aos-delay="300">
                <div class="mini-stat">
                    <div class="mini-icon" style="background:rgba(137,217,0,0.15); color:#5c9200;"><i class="fas fa-users"></i></div>
                    <div>
                        <h4 class="fw-bold mb-0"><?php echo $uniqueSenderCount; ?></h4>
                        <small class="text-muted">Unique Senders</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Messages Table -->
        <div class="card p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 px-2">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="fw-semibold small text-muted me-1">Show:</span>
                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill time-pill active" data-filter="all">All</button>
                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill time-pill" data-filter="week">This Week</button>
                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill time-pill" data-filter="month">This Month</button>
                </div>
                <button type="submit" form="bulkForm" name="bulk_action" value="delete" class="btn btn-sm btn-danger rounded-pill" onclick="return confirm('Delete the selected messages? This cannot be undone.');">
                    <i class="fas fa-trash me-1"></i> Delete Selected
                </button>
            </div>
            <div class="table-responsive">
                <form method="POST" id="bulkForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES); ?>">
                    <table id="messagesTable" class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAll"></th>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Package</th>
                                <th>Preview</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($messages)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-5">
                                    <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                                    <p class="text-muted mb-0">No messages yet</p>
                                </td>
                            </tr>
                            <?php else: foreach ($messages as $msg):
                                $ts = strtotime($msg['created_at']);
                                $isNew = $ts !== false && $ts >= ($now - 7 * 86400);
                            ?>
                            <tr class="<?php echo $isNew ? 'is-new-msg' : ''; ?>" data-created="<?php echo (int) $ts; ?>">
                                <td><input type="checkbox" name="selected[]" value="<?php echo (int) $msg['id']; ?>"></td>
                                <td><?php echo (int) $msg['id']; ?></td>
                                <td class="fw-semibold">
                                    <?php echo htmlspecialchars($msg['name'], ENT_QUOTES); ?>
                                    <?php if ($isNew): ?><span class="badge badge-new ms-2">NEW</span><?php endif; ?>
                                </td>
                                <td>
                                    <a href="mailto:<?php echo htmlspecialchars($msg['email'], ENT_QUOTES); ?>" class="text-decoration-none"><?php echo htmlspecialchars($msg['email'], ENT_QUOTES); ?></a>
                                    <i class="fas fa-copy copy-email-btn text-muted ms-1" title="Copy email" data-email="<?php echo htmlspecialchars($msg['email'], ENT_QUOTES); ?>"></i>
                                </td>
                                <td>
                                    <?php if (!empty($msg['phone'])): ?>
                                        <a href="tel:<?php echo htmlspecialchars($msg['phone'], ENT_QUOTES); ?>" class="text-decoration-none"><?php echo htmlspecialchars($msg['phone'], ENT_QUOTES); ?></a>
                                        <i class="fas fa-copy copy-phone-btn text-muted ms-1" title="Copy phone" data-phone="<?php echo htmlspecialchars($msg['phone'], ENT_QUOTES); ?>"></i>
                                    <?php else: ?>
                                        <span class="text-muted">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($msg['offer_title'])): ?>
                                        <span class="badge badge-package"><?php echo htmlspecialchars($msg['offer_title'], ENT_QUOTES); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">General</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php
                                    $preview = htmlspecialchars(substr($msg['message'], 0, 60), ENT_QUOTES);
                                    echo $preview . (strlen($msg['message']) > 60 ? '&hellip;' : '');
                                ?></td>
                                <td><?php echo $ts !== false ? date('d M Y, H:i', $ts) : htmlspecialchars($msg['created_at'], ENT_QUOTES); ?></td>
                                <td class="text-nowrap">
                                    <!--
                                        Only data-id is passed here. The full record (name/email/phone/
                                        message/etc.) is looked up client-side from the JSON payload
                                        below, which is far more reliable than stuffing long text with
                                        quotes/newlines into HTML data-* attributes.
                                    -->
                                    <button type="button" class="btn btn-sm btn-outline-primary view-msg"
                                        data-bs-toggle="modal" data-bs-target="#viewModal"
                                        data-id="<?php echo (int) $msg['id']; ?>">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <a href="mailto:<?php echo htmlspecialchars($msg['email'], ENT_QUOTES); ?>?subject=<?php echo urlencode('Re: ' . $msg['subject']); ?>"
                                       class="btn btn-sm btn-outline-success" title="Reply by email">
                                        <i class="fas fa-reply"></i>
                                    </a>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this message?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES); ?>">
                                        <input type="hidden" name="id" value="<?php echo (int) $msg['id']; ?>">
                                        <button type="submit" name="delete_single" class="btn btn-sm btn-outline-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </form>
            </div>
        </div>

<!-- View Modal -->
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 rounded-4 overflow-hidden">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-envelope me-2"></i> Message Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Name:</strong> <span id="modalName" class="fw-bold text-primary"></span></p>
                        <p><strong>Email:</strong> <a href="#" id="modalEmailLink"></a></p>
                        <p><strong>Phone:</strong> <a href="#" id="modalPhoneLink"></a></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Subject:</strong> <span id="modalSubject"></span></p>
                        <p><strong>Package:</strong> <span id="modalPackage" class="badge badge-package"></span></p>
                        <p><strong>Received:</strong> <span id="modalDate"></span></p>
                    </div>
                </div>
                <hr>
                <div class="bg-light p-4 rounded">
                    <p id="modalMessage" class="mb-0 lh-lg"></p>
                </div>
                <div class="text-end mt-4">
                    <a href="#" id="replyEmail" class="btn btn-primary me-2"><i class="fas fa-reply"></i> Reply Email</a>
                    <a href="#" id="replyWhatsApp" target="_blank" rel="noopener" class="btn btn-success"><i class="fab fa-whatsapp"></i> WhatsApp</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$extraScripts = <<<'HTML'
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.js"></script>
<script src="https://cdn.datatables.net/responsive/3.0.3/js/dataTables.responsive.js"></script>
<script src="https://cdn.datatables.net/responsive/3.0.3/js/responsive.bootstrap5.js"></script>
<script>
(function () {
    'use strict';

    // ---------------------------------------------------------------
    // Full message records, JSON-encoded server-side. We look records
    // up by id from this object instead of relying on many separate
    // HTML data-* attributes -- that avoids any risk of quotes,
    // newlines, or unicode in a message breaking an attribute value.
    // ---------------------------------------------------------------
    const MESSAGES = MESSAGES_JSON_PLACEHOLDER;
    const messagesById = {};
    MESSAGES.forEach(function (m) { messagesById[m.id] = m; });

    function setText(id, value) {
        const el = document.getElementById(id);
        if (el) el.textContent = value || '';
    }

    // Normalizes a stored phone number (local 0XXXXXXXXX, or
    // international +233XXXXXXXXX / 233XXXXXXXXX) into the digit
    // string wa.me expects.
    function toWhatsAppNumber(rawPhone) {
        const digits = (rawPhone || '').replace(/\D/g, '');
        if (!digits) return null;
        if (digits.startsWith('233') && digits.length >= 11) return digits;
        if (digits.startsWith('0')) return '233' + digits.slice(1);
        if (digits.length === 9) return '233' + digits; // bare local number, no leading 0
        return digits; // already looks international, or an unrecognized format
    }

    function copyToClipboard(text, iconEl) {
        if (!text || !iconEl) return;
        const done = function () {
            const original = iconEl.className;
            iconEl.className = 'fas fa-check text-success ms-1';
            setTimeout(function () { iconEl.className = original; }, 1200);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done).catch(function () { fallbackCopy(text, done); });
        } else {
            fallbackCopy(text, done);
        }
    }

    function fallbackCopy(text, done) {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); done(); } catch (err) { console.error('Copy failed:', err); }
        document.body.removeChild(ta);
    }

    // -----------------------------------------------------------------
    // Each section below is wrapped in its own try/catch and is fully
    // independent of the others. This is deliberate: if admin_header.php
    // or admin_footer.php already initializes DataTables on this same
    // table (a double-init throws "Cannot reinitialise DataTable"), that
    // error is now contained to section 1 only. The view-modal and
    // reply-button handling in section 3 -- which is the part that was
    // silently failing before -- will still bind and work correctly.
    // -----------------------------------------------------------------

    // ---------- 1. DataTable init (guarded against double-init) ----------
    let table = null;
    try {
        if (window.jQuery && $.fn.dataTable && $.fn.dataTable.isDataTable('#messagesTable')) {
            $('#messagesTable').DataTable().destroy();
        }
        table = new DataTable('#messagesTable', {
            responsive: true,
            order: [[7, 'desc']],
            pageLength: 15,
            columnDefs: [{ orderable: false, searchable: false, targets: [0, 8] }],
            language: { search: 'Search messages:' }
        });
    } catch (err) {
        console.error('DataTable init failed (table will still render, just unsorted/unpaginated):', err);
    }

    // ---------- 2. Time filter pills ----------
    try {
        let activeFilter = 'all';
        if (table) {
            DataTable.ext.search.push(function (settings, searchData, index) {
                if (activeFilter === 'all') return true;
                const row = table.row(index).node();
                const ts = parseInt(row.dataset.created, 10);
                if (!ts) return true;
                const now = Date.now() / 1000;
                if (activeFilter === 'week') return ts >= now - 7 * 86400;
                if (activeFilter === 'month') {
                    const d = new Date(ts * 1000);
                    const n = new Date();
                    return d.getFullYear() === n.getFullYear() && d.getMonth() === n.getMonth();
                }
                return true;
            });
        }
        document.querySelectorAll('.time-pill').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.time-pill').forEach(function (p) { p.classList.remove('active'); });
                btn.classList.add('active');
                activeFilter = btn.dataset.filter;
                if (table) table.draw();
            });
        });
    } catch (err) {
        console.error('Time filter setup failed:', err);
    }

    // ---------- 3. Select-all checkbox ----------
    try {
        const selectAll = document.getElementById('selectAll');
        if (selectAll) {
            selectAll.addEventListener('click', function () {
                document.querySelectorAll('input[name="selected[]"]').forEach(function (cb) { cb.checked = selectAll.checked; });
            });
        }
    } catch (err) {
        console.error('Select-all setup failed:', err);
    }

    // ---------- 4. Row actions: View modal + copy buttons ----------
    // Bound once, on document, with delegation -- so it survives every
    // DataTables redraw/sort/page/resize regardless of what happened above.
    try {
        document.addEventListener('click', function (e) {

            const viewBtn = e.target.closest('.view-msg');
            if (viewBtn) {
                try {
                    const msg = messagesById[viewBtn.dataset.id];
                    if (!msg) {
                        console.error('No message data found for id', viewBtn.dataset.id);
                        return;
                    }

                    setText('modalName', msg.name);

                    const emailLink = document.getElementById('modalEmailLink');
                    if (emailLink) {
                        emailLink.textContent = msg.email;
                        emailLink.href = 'mailto:' + msg.email;
                    }

                    const phoneLink = document.getElementById('modalPhoneLink');
                    if (phoneLink) {
                        phoneLink.textContent = msg.phone || 'Not provided';
                        phoneLink.href = msg.phone ? ('tel:' + msg.phone) : '#';
                    }

                    setText('modalSubject', msg.subject);

                    const packageEl = document.getElementById('modalPackage');
                    if (packageEl) packageEl.textContent = msg.offer_title || 'General inquiry';

                    setText('modalMessage', msg.message);

                    const dateEl = document.getElementById('modalDate');
                    if (dateEl) {
                        const d = new Date((msg.created_at || '').replace(' ', 'T'));
                        dateEl.textContent = isNaN(d.getTime()) ? (msg.created_at || '') : d.toLocaleString();
                    }

                    // Reply Email: prefill a quoted-reply body so replying takes one click.
                    const replyEmailBtn = document.getElementById('replyEmail');
                    if (replyEmailBtn) {
                        const quoted = '\n\n---\nOn ' + (msg.created_at || '') + ', ' + msg.name + ' wrote:\n> ' +
                            String(msg.message || '').replace(/\n/g, '\n> ');
                        replyEmailBtn.href = 'mailto:' + msg.email +
                            '?subject=' + encodeURIComponent('Re: ' + msg.subject) +
                            '&body=' + encodeURIComponent('Hi ' + msg.name + ',\n\n' + quoted);
                        replyEmailBtn.classList.remove('disabled');
                    }

                    // Reply WhatsApp
                    const waNumber = toWhatsAppNumber(msg.phone);
                    const waBtn = document.getElementById('replyWhatsApp');
                    if (waBtn) {
                        if (waNumber) {
                            waBtn.href = 'https://wa.me/' + waNumber + '?text=' +
                                encodeURIComponent('Hello ' + msg.name + '! Thank you for contacting Believe Travel Consult regarding: ' + msg.subject);
                            waBtn.classList.remove('disabled');
                            waBtn.removeAttribute('aria-disabled');
                            waBtn.title = '';
                        } else {
                            waBtn.href = '#';
                            waBtn.classList.add('disabled');
                            waBtn.setAttribute('aria-disabled', 'true');
                            waBtn.title = 'No phone number was provided for this contact';
                        }
                    }
                } catch (err) {
                    console.error('Failed to populate view modal:', err);
                }
                return;
            }

            const copyEmailBtn = e.target.closest('.copy-email-btn');
            if (copyEmailBtn) {
                e.preventDefault();
                copyToClipboard(copyEmailBtn.dataset.email, copyEmailBtn);
                return;
            }

            const copyPhoneBtn = e.target.closest('.copy-phone-btn');
            if (copyPhoneBtn) {
                e.preventDefault();
                copyToClipboard(copyPhoneBtn.dataset.phone, copyPhoneBtn);
                return;
            }
        });
    } catch (err) {
        console.error('Row-action delegation failed to bind:', err);
    }

    // ---------- 5. CSV export ----------
    try {
        const exportBtn = document.getElementById('exportCsvBtn');
        if (exportBtn) {
            exportBtn.addEventListener('click', function () {
                try {
                    const rows = [['ID', 'Name', 'Email', 'Phone', 'Subject', 'Package', 'Message', 'Date']];
                    MESSAGES.forEach(function (m) {
                        rows.push([m.id, m.name, m.email, m.phone, m.subject, m.offer_title || 'General', m.message, m.created_at]);
                    });
                    const csv = rows.map(function (r) {
                        return r.map(function (v) { return '"' + String(v == null ? '' : v).replace(/"/g, '""') + '"'; }).join(',');
                    }).join('\n');
                    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                    const link = document.createElement('a');
                    link.href = URL.createObjectURL(blob);
                    link.download = 'contact_messages_' + new Date().toISOString().slice(0, 10) + '.csv';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    URL.revokeObjectURL(link.href);
                } catch (err) {
                    console.error('CSV export failed:', err);
                    alert('Sorry, the export failed. Please check the browser console for details.');
                }
            });
        }
    } catch (err) {
        console.error('Export button setup failed:', err);
    }

})();
</script>
HTML;

// JSON_HEX_TAG/APOS/QUOT/AMP make it safe to embed inside a <script> block even
// if a message happens to contain characters like </script>, quotes, or & signs.
$extraScripts = str_replace(
    'MESSAGES_JSON_PLACEHOLDER',
    json_encode($messages, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP),
    $extraScripts
);

include 'admin_footer.php';