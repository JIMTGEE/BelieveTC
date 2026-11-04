<?php
// Guarded so it's safe whether or not includes/db.php or includes/header.php
// already start the session elsewhere.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db.php';
require_once 'includes/functions.php';
include 'includes/header.php';

$success = $error = '';
$offer_id = isset($_GET['offer']) ? (int)$_GET['offer'] : 0;
$offer_title = '';

if ($offer_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT title FROM offers WHERE id = ?");
        $stmt->execute([$offer_id]);
        $row = $stmt->fetch();
        $offer_title = $row ? htmlspecialchars($row['title'], ENT_QUOTES) : '';
        // Ignore a stale/invalid offer id from the query string
        if (!$row) $offer_id = 0;
    } catch (PDOException $e) {
        error_log('contact.php offer lookup failed: ' . $e->getMessage());
        $offer_id = 0;
        $offer_title = '';
    }
}

// A render timestamp so we can silently reject submissions that arrive
// suspiciously fast (a strong bot signal). Kept in the session, not the
// form, so it can't just be copied back by a script.
if (empty($_SESSION['contact_form_rendered_at'])) {
    $_SESSION['contact_form_rendered_at'] = time();
}

// Process form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Truncate to the schema's column limits (name varchar(100), phone
    // varchar(30)) up front, so a too-long value never even reaches the
    // validation/insert step below and can't trigger a strict-mode
    // "Data too long for column" error from MariaDB.
    $name    = mb_substr(trim($_POST['name'] ?? ''), 0, 100);
    $email   = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $phone   = mb_substr(trim($_POST['phone'] ?? ''), 0, 30);
    $message = trim($_POST['message'] ?? '');
    $offer_ref = (int)($_POST['offer_id'] ?? 0);

    // Honeypot: a field real visitors never see or fill in. Any value here
    // means it was a bot. We show a generic success message and skip the
    // insert/email entirely, so the bot doesn't learn its submission failed.
    $honeypot = trim($_POST['website'] ?? '');
    $renderedAt = (int)($_SESSION['contact_form_rendered_at'] ?? 0);
    $submittedTooFast = $renderedAt > 0 && (time() - $renderedAt) < 3;
    $isLikelyBot = $honeypot !== '' || $submittedTooFast;

    if ($isLikelyBot) {
        $success = 'Thank you! We\u2019ve received your message and will contact you within 1 hour.';
        $_POST = [];
        unset($_SESSION['contact_form_rendered_at']);
    } elseif (!$name || !$email || !$phone || !$message) {
        $error = 'Please fill in all required fields.';
    } elseif ($email === false) {
        $error = 'Please enter a valid email address.';
    } elseif (mb_strlen($email) > 255) {
        // varchar(255) on the email column. Truncating an email would make
        // it invalid, so this has to be a rejection, not a silent cut.
        $error = 'That email address is too long. Please double-check it.';
    } elseif (!preg_match('/^[0-9+()\-\s]{6,20}$/', $phone)) {
        $error = 'Please enter a valid phone number.';
    } else {
        // Re-look-up the offer title server-side (don't trust the hidden field's
        // title, only its id) so the subject line/email body can't be spoofed.
        $subject_offer_title = '';
        if ($offer_ref > 0) {
            try {
                $stmt = $pdo->prepare("SELECT title FROM offers WHERE id = ?");
                $stmt->execute([$offer_ref]);
                $row = $stmt->fetch();
                if ($row) {
                    $subject_offer_title = $row['title'];
                } else {
                    $offer_ref = 0; // invalid id, don't attach it to the message
                }
            } catch (PDOException $e) {
                error_log('contact.php offer re-lookup failed: ' . $e->getMessage());
                $offer_ref = 0;
                $subject_offer_title = '';
            }
        }

        $subject = $subject_offer_title
            ? 'Inquiry: ' . $subject_offer_title
            : 'General Contact';
        // subject column is varchar(255); an unusually long offer title
        // could otherwise overflow it and trigger a strict-mode DB error.
        $subject = mb_substr($subject, 0, 255);

        try {
            $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, phone, subject, message, offer_id, created_at)
                                   VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([
                $name,
                $email,
                $phone,
                $subject,
                $message,
                $offer_ref ?: null,
            ]);

            // Defense-in-depth against header injection, even though
            // FILTER_VALIDATE_EMAIL already rejects emails containing
            // CR/LF characters.
            $safeEmail = str_replace(["\r", "\n"], '', $email);
            $safeName  = str_replace(["\r", "\n"], '', $name);

            $to = 'believetravelconsult903@gmail.com';
            $mail_subject = 'New Inquiry - ' . ($subject_offer_title ?: 'General Contact');
            $body  = "New message from website\n\n";
            $body .= "Name: $safeName\nEmail: $safeEmail\nPhone: $phone\n\nMessage:\n$message";
            if ($offer_ref) $body .= "\n\nInterested Package: $subject_offer_title (ID: $offer_ref)";

            $headers = "From: $safeEmail\r\nReply-To: $safeEmail\r\nContent-Type: text/plain; charset=UTF-8";

            $mailSent = @mail($to, $mail_subject, $body, $headers);
            if (!$mailSent) {
                // The message is already safely stored in the database at this
                // point, so a mail() failure (common on shared hosting without
                // SMTP configured) is logged, not shown as an error to the user.
                error_log('contact.php: mail() returned false for submission from ' . $safeEmail);
            }

            $success = 'Thank you! We\u2019ve received your message and will contact you within 1 hour.';
            $_POST = []; // Clear form
            unset($_SESSION['contact_form_rendered_at']);
        } catch (PDOException $e) {
            error_log('contact.php insert failed: ' . $e->getMessage());
            $error = 'Something went wrong. Please try WhatsApp instead.';
        }
    }
}
?>

<style>
    :root {
        --btc-navy: #033754;
        --btc-green: #89d900;
        --btc-blue: #10b6d7;
    }

    /* ——— PAGE HEADER (minimal, text-only) ——— */
    .contact-page-header {
        padding: 2.25rem 0 1.75rem;
        border-bottom: 1px solid #e7ecef;
        background: #fff;
    }
    .contact-page-header .eyebrow {
        color: var(--btc-green);
        letter-spacing: .12em;
        text-transform: uppercase;
        font-size: .78rem;
        font-weight: 700;
    }
    .contact-page-header h1 {
        color: var(--btc-navy);
        font-weight: 800;
        font-size: clamp(1.6rem, 2.4vw, 2.1rem);
        margin: .3rem 0 .4rem;
        position: relative;
        padding-bottom: .6rem;
    }
    .contact-page-header h1::after {
        content: "";
        position: absolute;
        left: 0; bottom: 0;
        width: 46px; height: 3px;
        background: var(--btc-green);
        border-radius: 3px;
    }
    .contact-page-header p {
        color: #5b6b74;
        max-width: 640px;
        margin-bottom: 1.1rem;
        font-size: .98rem;
    }
    .contact-page-header .quick-actions {
        display: flex;
        gap: .75rem;
        flex-wrap: wrap;
    }
    .contact-page-header .btn-quick {
        border-radius: 999px;
        font-weight: 700;
        font-size: .9rem;
        padding: .55rem 1.3rem;
    }
    .contact-page-header .btn-quick-whatsapp {
        background: var(--btc-green);
        color: var(--btc-navy);
        border: none;
    }
    .contact-page-header .btn-quick-whatsapp:hover { color: var(--btc-navy); filter: brightness(1.05); }
    .contact-page-header .btn-quick-outline {
        border: 1.5px solid #dfe6ea;
        color: var(--btc-navy);
        background: #fff;
    }
    .contact-page-header .btn-quick-outline:hover { border-color: var(--btc-green); color: var(--btc-navy); }

    /* ——— FORM CARD ——— */
    .contact-form-card {
        background: #fff;
        border-radius: 20px;
        box-shadow: 0 10px 34px rgba(3,55,84,.08);
        border: 1px solid #eef1f3;
    }
    .contact-form-card .form-control {
        border: 1.5px solid #e3e9ec;
        padding: .75rem 1.15rem;
    }
    .contact-form-card .form-control:focus {
        border-color: var(--btc-green);
        box-shadow: 0 0 0 .2rem rgba(137,217,0,.15);
    }
    .contact-form-card textarea.form-control { border-radius: 18px; }
    .btn-send {
        background: var(--btc-blue);
        color: #fff;
        font-weight: 800;
        font-size: 1.05rem;
        border-radius: 999px;
        padding: .85rem 2.5rem;
        border: none;
        transition: transform .2s ease, box-shadow .2s ease;
    }
    .btn-send:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 24px rgba(16,182,215,.35);
        color: #fff;
    }

    /* ——— INFO CARDS ——— */
    .contact-info-card {
        display: flex;
        align-items: center;
        gap: 1.1rem;
        padding: 1.15rem 1.3rem;
        background: #fff;
        border: 1px solid #eef1f3;
        border-radius: 16px;
        box-shadow: 0 4px 14px rgba(3,55,84,.05);
        transition: transform .2s ease, box-shadow .2s ease;
    }
    .contact-info-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 24px rgba(3,55,84,.1);
    }
    .contact-info-card .icon-wrap {
        flex-shrink: 0;
        width: 48px; height: 48px;
        border-radius: 12px;
        background: rgba(137,217,0,.12);
        display: flex; align-items: center; justify-content: center;
    }
    .contact-info-card .icon-wrap img { width: 24px; height: 24px; }
    .contact-info-card h5 { color: var(--btc-navy); font-weight: 700; margin-bottom: .15rem; }

    /* ——— TRUST STRIP ——— */
    .trust-strip {
        background: linear-gradient(135deg, #033754 0%, #0a6c8a 100%);
    }
    .trust-strip .trust-num { font-weight: 800; }
</style>

<!-- ——— PAGE HEADER (minimal, text-only) ——— -->
<section class="contact-page-header">
    <div class="container">
        <div class="row">
            <div class="col-lg-9">
                <div class="eyebrow">Get In Touch</div>
                <h1>Let's Plan Your Next Move</h1>
                <p>Jobs, visas, travel, and relocation support — reach out and our team will respond within the hour.</p>
                <div class="quick-actions">
                    <a href="https://wa.me/233596204991?text=Hi!%20I%20need%20help%20with%20a%20job%2C%20visa%2C%20or%20relocation%20plan"
                       class="btn btn-quick btn-quick-whatsapp">
                        <i class="fab fa-whatsapp me-2"></i>Chat on WhatsApp
                    </a>
                    <a href="#contact-form" class="btn btn-quick btn-quick-outline">
                        Send a Message ↓
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- MAIN CONTACT SECTION -->
<section class="py-6 bg-light" id="contact-form">
    <div class="container">
        <div class="row g-5 align-items-start">

            <!-- Contact Form -->
            <div class="col-lg-7">
                <div class="contact-form-card p-4 p-xl-5">
                    <h2 class="h3 fw-bold mb-4" style="color:var(--btc-navy);">Send Us a Message</h2>

                    <?php if ($offer_title): ?>
                        <div class="alert alert-info rounded-4 mb-4">
                            <strong>Regarding:</strong> <?= $offer_title ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success rounded-4 fs-5 text-center py-4">
                            <i class="fas fa-check-circle fa-2x mb-3"></i><br>
                            <?= $success ?>
                        </div>
                    <?php elseif ($error): ?>
                        <div class="alert alert-danger rounded-4"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
                    <?php endif; ?>

                    <form method="POST" class="row g-4" autocomplete="off">
                        <input type="hidden" name="offer_id" value="<?= $offer_id ?>">

                        <!-- Honeypot field: hidden from real users via CSS, left blank by
                             them, but often auto-filled by bots. Do not remove or rename. -->
                        <div style="position:absolute; left:-9999px; top:-9999px;" aria-hidden="true">
                            <label for="website">Leave this field empty</label>
                            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off" value="">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Full Name *</label>
                            <input type="text" name="name" class="form-control form-control-lg rounded-pill" maxlength="100"
                                   value="<?= htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Phone Number *</label>
                            <input type="text" name="phone" class="form-control form-control-lg rounded-pill" maxlength="30"
                                   placeholder="+233..." value="<?= htmlspecialchars($_POST['phone'] ?? '', ENT_QUOTES) ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Email Address *</label>
                            <input type="email" name="email" class="form-control form-control-lg rounded-pill" maxlength="255"
                                   value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES) ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Your Message *</label>
                            <textarea name="message" rows="6" class="form-control rounded-4"
                                      placeholder="Tell us about your plans (job type, timeline, budget...)" required><?= htmlspecialchars($_POST['message'] ?? '', ENT_QUOTES) ?></textarea>
                        </div>
                        <div class="col-12 text-center">
                            <button type="submit" class="btn btn-send mt-2">
                                <i class="fas fa-paper-plane me-2"></i> Send Message Now
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Contact Info + Map -->
            <div class="col-lg-5">
                <!-- Info Cards -->
                <div class="d-flex flex-column gap-3 mb-4">
                    <div class="contact-info-card">
                        <div class="icon-wrap">
                            <img src="assets/images/phone-call.svg" alt="Call">
                        </div>
                        <div>
                            <h5 class="mb-1">Call or WhatsApp</h5>
                            <a href="tel:+233596204991" class="text-dark text-decoration-none">+233 596 204 991</a>
                        </div>
                    </div>
                    <div class="contact-info-card">
                        <div class="icon-wrap">
                            <img src="assets/images/message.svg" alt="Email">
                        </div>
                        <div>
                            <h5 class="mb-1">Email Us</h5>
                            <a href="mailto:believetravelconsult903@gmail.com" class="text-decoration-none">
                                believetravelconsult903@gmail.com
                            </a>
                        </div>
                    </div>
                    <div class="contact-info-card align-items-start">
                        <div class="icon-wrap">
                            <img src="assets/images/placeholder.svg" alt="Location">
                        </div>
                        <div>
                            <h5 class="mb-2">Our Branches</h5>
                            <p class="mb-1 small text-muted">Tema Golf City</p>
                            <p class="mb-1 small text-muted">Kumasi Ahodwo</p>
                            <p class="mb-0 small text-muted">Kasoa, Adam Nana</p>
                        </div>
                    </div>
                </div>

                <!-- Google Map -->
                <div class="ratio ratio-16x9 rounded-4 overflow-hidden shadow-sm">
                    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3970.260753391552!2d-0.0148!3d5.7042!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0xfdf9e6b00000001%3A0xabc123!2sDevtraco%20Estates!5e0!3m2!1sen!2sgh!4v1735000000000"
                            width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- TRUST STRIP + FINAL CTA -->
<section class="py-6 text-white text-center trust-strip">
    <div class="container">
        <h2 class="h3 fw-bold mb-5">Why 1,500+ Clients Trust Us</h2>
        <div class="row g-4 justify-content-center">
            <div class="col-6 col-md-3">
                <h3 class="display-6 trust-num" style="color:#89d900;">1500+</h3>
                <p class="mb-0 small opacity-75">Clients Placed</p>
            </div>
            <div class="col-6 col-md-3">
                <h3 class="display-6 trust-num" style="color:#10b6d7;">98%</h3>
                <p class="mb-0 small opacity-75">Success Rate</p>
            </div>
            <div class="col-6 col-md-3">
                <h3 class="display-6 trust-num">24/7</h3>
                <p class="mb-0 small opacity-75">Support Team</p>
            </div>
            <div class="col-6 col-md-3">
                <h3 class="display-6 trust-num" style="color:#89d900;">8+ Years</h3>
                <p class="mb-0 small opacity-75">Experience</p>
            </div>
        </div>

        <a href="https://wa.me/233596204991?text=Hi!%20I%20saw%20your%20contact%20page%20-%20please%20help%20me%20with%20a%20job%2C%20visa%2C%20or%20relocation%20plan!"
           class="btn btn-lg rounded-pill px-5 py-3 shadow-lg mt-5"
           style="background:#89d900; color:#033754; font-weight:900;">
            <i class="fab fa-whatsapp fa-2x me-3"></i> Start Now on WhatsApp
        </a>
    </div>
</section>

<?php include 'includes/footer.php'; ?>