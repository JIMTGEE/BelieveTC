<?php
require_once __DIR__ . '/includes/db.php';

/* =====================================================================
 * AJAX SUBMISSION HANDLER
 * When the form is submitted, JS posts back to THIS SAME FILE with
 * ajax_submit=1. We validate, upload the documents, generate a unique
 * ref code, insert into `passport_applications`, and return JSON —
 * no redirect, no separate page.
 * ===================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_submit'])) {
    header('Content-Type: application/json');

    function json_fail(string $message, int $code = 400): void
    {
        http_response_code($code);
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }

    function req_field(string $name): string
    {
        if (!isset($_POST[$name]) || trim($_POST[$name]) === '') {
            json_fail("Missing required field: $name");
        }
        return trim($_POST[$name]);
    }

    // ---- Text fields ----
    $applicant_name                = req_field('applicant_name');
    $dob                           = req_field('dob');
    $place_of_birth                = req_field('place_of_birth');
    $phone_primary                 = req_field('phone_primary');
    $residential_address           = req_field('residential_address');

    $father_name                   = req_field('father_name');
    $father_hometown                = req_field('father_hometown');
    $father_residential_address    = req_field('father_residential_address');
    $father_phone                  = req_field('father_phone');

    $mother_name                   = req_field('mother_name');
    $mother_hometown                = req_field('mother_hometown');
    $mother_residential_address    = req_field('mother_residential_address');

    $grandmother_name              = req_field('grandmother_name');
    $grandmother_hometown           = req_field('grandmother_hometown');
    $grandfather_name              = req_field('grandfather_name');
    $grandfather_hometown           = req_field('grandfather_hometown');

    $guarantor_name                 = req_field('guarantor_name');
    $guarantor_occupation           = req_field('guarantor_occupation');
    $guarantor_phone                = req_field('guarantor_phone');
    $guarantor_residential_address  = req_field('guarantor_residential_address');

    $declaration_accepted = isset($_POST['declaration']) ? 1 : 0;
    if (!$declaration_accepted) {
        json_fail("You must accept the declaration before submitting.");
    }

    if (!DateTime::createFromFormat('Y-m-d', $dob)) {
        json_fail("Date of birth is invalid.");
    }

    $package = 'Express - GH₵1,800';

    // ---- Unique ref code ----
    function generate_ref_code(PDO $pdo): string
    {
        $check = $pdo->prepare("SELECT id FROM passport_applications WHERE ref_code = ? LIMIT 1");
        do {
            $ref = 'BTC-PASSPORT-' . date('Y') . '-' . random_int(100000, 999999);
            $check->execute([$ref]);
        } while ($check->fetch());
        return $ref;
    }
    $ref_code = generate_ref_code($pdo);

    // ---- File uploads ----
    $uploadRoot = __DIR__ . '/uploads/passport_applications/' . $ref_code;
    if (!is_dir($uploadRoot)) {
        mkdir($uploadRoot, 0755, true);
    }

    $allowedExt  = ['jpg', 'jpeg', 'png', 'pdf'];
    $maxFileSize = 5 * 1024 * 1024; // 5MB

    function store_upload(array $file, string $uploadRoot, string $ref_code, array $allowedExt, int $maxFileSize, string $label): string
    {
        if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            json_fail("$label is required.");
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            json_fail("There was a problem uploading $label. Please try again.");
        }
        if ($file['size'] > $maxFileSize) {
            json_fail("$label must be smaller than 5MB.");
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            json_fail("$label must be a JPG, PNG, or PDF file.");
        }
        $safeName = bin2hex(random_bytes(8)) . '.' . $ext;
        $destPath = $uploadRoot . '/' . $safeName;
        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            json_fail("Failed to save $label. Please try again.");
        }
        return 'uploads/passport_applications/' . $ref_code . '/' . $safeName;
    }

    $birth_certificate = store_upload($_FILES['birth_certificate'], $uploadRoot, $ref_code, $allowedExt, $maxFileSize, 'Birth Certificate');
    $guarantor_id       = store_upload($_FILES['guarantor_id'], $uploadRoot, $ref_code, $allowedExt, $maxFileSize, "Guarantor's ID");
    $proof_address      = store_upload($_FILES['proof_address'], $uploadRoot, $ref_code, $allowedExt, $maxFileSize, 'Proof of Address');

    $photoPaths = [];
    if (!empty($_FILES['passport_photos']['name'][0])) {
        $count = count($_FILES['passport_photos']['name']);
        for ($i = 0; $i < $count; $i++) {
            $singleFile = [
                'name'     => $_FILES['passport_photos']['name'][$i],
                'type'     => $_FILES['passport_photos']['type'][$i],
                'tmp_name' => $_FILES['passport_photos']['tmp_name'][$i],
                'error'    => $_FILES['passport_photos']['error'][$i],
                'size'     => $_FILES['passport_photos']['size'][$i],
            ];
            $photoPaths[] = store_upload($singleFile, $uploadRoot, $ref_code, $allowedExt, $maxFileSize, 'Passport Photo');
        }
    }
    if (count($photoPaths) < 2) {
        json_fail("Please upload at least 2 passport photos.");
    }
    $passport_photos = json_encode($photoPaths);

    // ---- Insert into DB ----
    try {
        $stmt = $pdo->prepare("
            INSERT INTO passport_applications (
                ref_code, applicant_name, dob, place_of_birth, phone_primary, residential_address,
                father_name, father_hometown, father_residential_address, father_phone,
                mother_name, mother_hometown, mother_residential_address,
                grandmother_name, grandmother_hometown, grandfather_name, grandfather_hometown,
                guarantor_name, guarantor_occupation, guarantor_phone, guarantor_residential_address,
                declaration_accepted, package,
                birth_certificate, passport_photos, guarantor_id, proof_address,
                status
            ) VALUES (
                :ref_code, :applicant_name, :dob, :place_of_birth, :phone_primary, :residential_address,
                :father_name, :father_hometown, :father_residential_address, :father_phone,
                :mother_name, :mother_hometown, :mother_residential_address,
                :grandmother_name, :grandmother_hometown, :grandfather_name, :grandfather_hometown,
                :guarantor_name, :guarantor_occupation, :guarantor_phone, :guarantor_residential_address,
                :declaration_accepted, :package,
                :birth_certificate, :passport_photos, :guarantor_id, :proof_address,
                'Pending'
            )
        ");
        $stmt->execute([
            ':ref_code'                       => $ref_code,
            ':applicant_name'                 => $applicant_name,
            ':dob'                             => $dob,
            ':place_of_birth'                  => $place_of_birth,
            ':phone_primary'                   => $phone_primary,
            ':residential_address'             => $residential_address,
            ':father_name'                     => $father_name,
            ':father_hometown'                 => $father_hometown,
            ':father_residential_address'      => $father_residential_address,
            ':father_phone'                    => $father_phone,
            ':mother_name'                     => $mother_name,
            ':mother_hometown'                 => $mother_hometown,
            ':mother_residential_address'      => $mother_residential_address,
            ':grandmother_name'                => $grandmother_name,
            ':grandmother_hometown'            => $grandmother_hometown,
            ':grandfather_name'                => $grandfather_name,
            ':grandfather_hometown'            => $grandfather_hometown,
            ':guarantor_name'                  => $guarantor_name,
            ':guarantor_occupation'            => $guarantor_occupation,
            ':guarantor_phone'                 => $guarantor_phone,
            ':guarantor_residential_address'   => $guarantor_residential_address,
            ':declaration_accepted'            => $declaration_accepted,
            ':package'                         => $package,
            ':birth_certificate'               => $birth_certificate,
            ':passport_photos'                 => $passport_photos,
            ':guarantor_id'                    => $guarantor_id,
            ':proof_address'                   => $proof_address,
        ]);
    } catch (PDOException $e) {
        error_log("Passport application insert failed: " . $e->getMessage());
        json_fail("We couldn't save your application due to a technical issue. Please try again or contact us on WhatsApp.", 500);
    }

    echo json_encode([
        'success'   => true,
        'ref_code'  => $ref_code,
        'status'    => 'Pending',
        'submitted' => date('d M Y, g:i A'),
    ]);
    exit;
}

include 'includes/header.php';
?>

<!-- OFFICIAL PASSPORT APPLICATION FORM -->
<section class="py-5 bg-light" style="min-height: 100vh;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-11 col-xl-10">

                <div class="bg-white overflow-hidden position-relative rounded-4 print-card"
                     style="border: 1px solid #dfe4e8; box-shadow: 0 30px 80px rgba(3,55,84,0.12);" id="formCard">

                    <div class="top-accent-bar"></div>

                    <!-- Watermark -->
                    <div class="position-absolute top-50 start-50 translate-middle pointer-events-none z-0 watermark">
                        <img src="assets/images/logo_new.png" alt="" style="width: 600px;">
                    </div>

                    <!-- LETTERHEAD -->
                    <div class="position-relative z-2 letterhead">
                        <div class="letterhead-top d-flex justify-content-between align-items-start flex-wrap gap-3">
                            <div class="d-flex align-items-center gap-3">
                                <img src="assets/images/logo_new.png" alt="Believe Travel Consult" class="letterhead-logo">
                                <div>
                                    <div class="brand-name">Believe Travel Consult</div>
                                    <div class="brand-tagline">Authorized Passport Processing Agent</div>
                                </div>
                            </div>
                            <div class="letterhead-contact text-md-end no-print">
                                <div><i class="fas fa-location-dot"></i> Tema Golf City &middot; Kumasi Ahodwo &middot; Kasoa Adam Nana</div>
                                <div><i class="fas fa-phone"></i> +233 596 204 991</div>
                            </div>
                        </div>

                        <div class="letterhead-divider"></div>

                        <div class="letterhead-title text-center">
                            <h1 id="headerTitle">Ghana Passport Application Form</h1>
                            <p class="letterhead-subtitle" id="headerSubtitle">Express Processing &middot; 3&ndash;7 Business Days</p>
                        </div>
                    </div>

                    <!-- Progress rail -->
                    <div class="px-5 pt-4 no-print" id="progressRail">
                        <div class="progress-rail d-flex justify-content-between flex-wrap gap-2">
                            <span class="rail-step active" data-target="sec-1">1. Applicant</span>
                            <span class="rail-step" data-target="sec-2">2. Father</span>
                            <span class="rail-step" data-target="sec-3">3. Mother &amp; Grandparents</span>
                            <span class="rail-step" data-target="sec-4">4. Guarantor</span>
                            <span class="rail-step" data-target="sec-5">5. Documents</span>
                            <span class="rail-step" data-target="sec-6">6. Confirm</span>
                        </div>
                    </div>

                    <!-- Inline alert for AJAX errors -->
                    <div class="px-5 pt-4">
                        <div class="alert alert-danger d-none no-print" id="formAlert" role="alert"></div>
                    </div>

                    <!-- FORM BODY -->
                    <div class="p-5 p-xl-7 position-relative z-2" id="formBody">

                        <form method="POST" action="passport_form.php" class="official-form" enctype="multipart/form-data" id="passportForm">
                            <input type="hidden" name="ajax_submit" value="1">

                            <!-- Reference Number preview -->
                            <div class="text-end mb-4 no-print">
                                <small class="text-muted">Ref: BTC/PASSPORT/2026/</small>
                                <strong class="text-primary fs-5" id="ref-number">AUTO-GENERATED</strong>
                            </div>

                            <!-- SECTION 1: Applicant Information -->
                            <div class="mb-5" id="sec-1">
                                <h5 class="fw-bold mb-3 section-title">
                                    <span class="step-badge">1</span> Applicant Information
                                </h5>
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold text-muted">Full Name (As in Birth Certificate)</label>
                                        <input type="text" name="applicant_name" class="form-control form-control-lg" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold text-muted">Date of Birth</label>
                                        <input type="date" name="dob" class="form-control form-control-lg" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold text-muted">Place of Birth</label>
                                        <input type="text" name="place_of_birth" class="form-control form-control-lg" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold text-muted">Primary Phone</label>
                                        <input type="tel" name="phone_primary" class="form-control form-control-lg" required placeholder="+233 ...">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-semibold text-muted">Residential Address in Ghana</label>
                                        <textarea name="residential_address" rows="2" class="form-control form-control-lg" required></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- SECTION 2: Father's Information -->
                            <div class="mb-5" id="sec-2">
                                <h5 class="fw-bold mb-3 section-title">
                                    <span class="step-badge">2</span> Father's Information
                                </h5>
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold text-muted">Full Name</label>
                                        <input type="text" name="father_name" class="form-control form-control-lg" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold text-muted">Hometown</label>
                                        <input type="text" name="father_hometown" class="form-control form-control-lg" required>
                                    </div>
                                    <div class="col-md-8">
                                        <label class="form-label fw-semibold text-muted">Residential Address</label>
                                        <input type="text" name="father_residential_address" class="form-control form-control-lg" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold text-muted">Telephone</label>
                                        <input type="tel" name="father_phone" class="form-control form-control-lg" required>
                                    </div>
                                </div>
                            </div>

                            <!-- SECTION 3: Mother's & Grandparents' Information -->
                            <div class="mb-5" id="sec-3">
                                <h5 class="fw-bold mb-3 section-title">
                                    <span class="step-badge">3</span> Mother's &amp; Grandparents' Information
                                </h5>
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold text-muted">Mother's Full Name (Maiden)</label>
                                        <input type="text" name="mother_name" class="form-control form-control-lg" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold text-muted">Mother's Hometown</label>
                                        <input type="text" name="mother_hometown" class="form-control form-control-lg" required>
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label fw-semibold text-muted">Mother's Residential Address</label>
                                        <input type="text" name="mother_residential_address" class="form-control form-control-lg" required>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold text-muted">Grandmother's Name</label>
                                        <input type="text" name="grandmother_name" class="form-control form-control-lg" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold text-muted">Grandmother's Hometown</label>
                                        <input type="text" name="grandmother_hometown" class="form-control form-control-lg" required>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold text-muted">Grandfather's Name</label>
                                        <input type="text" name="grandfather_name" class="form-control form-control-lg" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold text-muted">Grandfather's Hometown</label>
                                        <input type="text" name="grandfather_hometown" class="form-control form-control-lg" required>
                                    </div>
                                </div>
                            </div>

                            <!-- SECTION 4: Guarantor Information -->
                            <div class="mb-5" id="sec-4">
                                <h5 class="fw-bold mb-3 section-title">
                                    <span class="step-badge">4</span> Guarantor Information (Ghanaian with Valid ID)
                                </h5>
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold text-muted">Full Name</label>
                                        <input type="text" name="guarantor_name" class="form-control form-control-lg" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold text-muted">Occupation</label>
                                        <input type="text" name="guarantor_occupation" class="form-control form-control-lg" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold text-muted">Telephone</label>
                                        <input type="tel" name="guarantor_phone" class="form-control form-control-lg" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-semibold text-muted">Residential Address</label>
                                        <textarea name="guarantor_residential_address" rows="2" class="form-control form-control-lg" required></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- SECTION 5: Document Uploads -->
                            <div class="mb-5 no-print" id="sec-5">
                                <h5 class="fw-bold mb-3 section-title">
                                    <span class="step-badge">5</span> Required Documents
                                </h5>
                                <p class="text-muted small mb-3">Accepted formats: JPG, PNG, PDF &bull; Max 5MB per file</p>
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold text-muted">Birth Certificate</label>
                                        <input type="file" name="birth_certificate" class="form-control form-control-lg" accept="image/*,application/pdf" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold text-muted">2 Passport Photos (White Background)</label>
                                        <input type="file" name="passport_photos[]" class="form-control form-control-lg" accept="image/*" multiple required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold text-muted">Guarantor's ID</label>
                                        <input type="file" name="guarantor_id" class="form-control form-control-lg" accept="image/*,application/pdf" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold text-muted">Proof of Address (Utility Bill)</label>
                                        <input type="file" name="proof_address" class="form-control form-control-lg" accept="image/*,application/pdf" required>
                                    </div>
                                </div>
                            </div>

                            <!-- SECTION 6: Declaration & Price -->
                            <div class="bg-light p-4 rounded-4 mb-5 border" style="border-color: #10b6d7;" id="sec-6">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" name="declaration" required>
                                            <label class="form-check-label fw-semibold">I declare all information is true and authorize Believe Travel Consult to process my application (Fee: GH₵1,800 - Express)</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4 text-center">
                                        <div class="bg-white p-3 rounded-3 shadow-sm">
                                            <p class="small text-muted mb-1">Express Package</p>
                                            <h4 class="fw-bold mb-0" style="color:#89d900;">GH₵1,800</h4>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- SUBMIT -->
                            <div class="text-center no-print">
                                <button type="submit" class="btn btn-lg rounded-pill px-6 py-4 fw-bold text-white shadow-lg" id="submitBtn"
                                        style="background:#033754; font-size:1.3rem;">
                                    <i class="fas fa-paper-plane me-3"></i> <span id="submitLabel">SUBMIT APPLICATION</span>
                                </button>
                                <p class="mt-3 text-muted small">
                                    <i class="fas fa-lock me-2"></i> Secure &amp; Confidential &bull; Processed via Official Channels
                                </p>
                            </div>

                        </form>
                    </div>

                    <!-- RECEIPT (hidden until a successful AJAX save; then replaces the form for viewing/printing) -->
                    <div class="p-5 p-xl-7 position-relative z-2 d-none" id="receiptBody">
                        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                            <div>
                                <small class="text-muted d-block">Reference Code</small>
                                <strong class="fs-4" style="color:#033754;" id="receiptRef">—</strong>
                            </div>
                            <div class="text-end">
                                <small class="text-muted d-block">Status</small>
                                <span class="badge rounded-pill px-3 py-2" style="background:#89d900; color:#033754; font-size:0.9rem;" id="receiptStatus">Pending</span>
                            </div>
                            <div class="text-end">
                                <small class="text-muted d-block">Submitted</small>
                                <strong id="receiptDate">—</strong>
                            </div>
                        </div>
                        <div class="bg-light p-4 rounded-4 border text-center mb-4" style="border-color:#10b6d7;">
                            <p class="mb-0 fw-semibold" style="color:#033754;">
                                Your application has been received and saved. Keep your reference code for tracking and quote it on WhatsApp.
                            </p>
                        </div>
                        <div class="text-center no-print">
                           
                            <button type="button" class="btn btn-outline-secondary rounded-pill px-4" id="newApplicationBtn">
                                <i class="fas fa-plus me-2"></i> New Application
                            </button>
                        </div>
                    </div>

                    <!-- Compact Footer -->
                    <div class="text-center py-4 border-top" style="background:#f8f9fa;">
                        <p class="small text-muted mb-0">
                            <strong>Believe Travel Consult</strong> &bull; Authorized Partner: Innovation Group Ltd., Moativ Employment Services, LAVAJET Company<br>
                            Email: believetravelconsult903@gmail.com
                        </p>
                    </div>
                </div>

                <!-- WhatsApp Button -->
                <a href="https://wa.me/233596204991?text=Hi! I just submitted my Passport Application Form."
                   class="btn btn-success btn-lg rounded-pill shadow-lg position-fixed bottom-0 end-0 m-4 z-1050 no-print"
                   style="background:#89d900; color:#033754;" id="whatsappBtn">
                    <i class="fab fa-whatsapp fa-2x me-2"></i> Chat Now
                </a>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Cosmetic reference preview only, shown until the real save happens
    const ref = "BTC-PASSPORT-" + new Date().getFullYear() + "-" + Math.floor(100000 + Math.random() * 900000);
    const refEl = document.getElementById("ref-number");
    if (refEl) refEl.textContent = ref;

    // Progress rail: click-to-scroll + active highlight
    const steps = Array.from(document.querySelectorAll(".rail-step"));
    const sections = steps.map(s => document.getElementById(s.dataset.target)).filter(Boolean);
    if (sections.length) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const idx = sections.indexOf(entry.target);
                    steps.forEach(s => s.classList.remove("active"));
                    if (steps[idx]) steps[idx].classList.add("active");
                }
            });
        }, { rootMargin: "-40% 0px -50% 0px" });
        sections.forEach(sec => observer.observe(sec));
    }
    steps.forEach(step => {
        step.addEventListener("click", () => {
            const target = document.getElementById(step.dataset.target);
            if (target) target.scrollIntoView({ behavior: "smooth", block: "start" });
        });
    });

    // AJAX submit — saves straight to the database, no page reload/redirect
    const form        = document.getElementById("passportForm");
    const submitBtn    = document.getElementById("submitBtn");
    const submitLabel  = document.getElementById("submitLabel");
    const alertBox     = document.getElementById("formAlert");
    const formBody     = document.getElementById("formBody");
    const receiptBody  = document.getElementById("receiptBody");
    const progressRail = document.getElementById("progressRail");
    const headerTitle  = document.getElementById("headerTitle");
    const headerSub    = document.getElementById("headerSubtitle");

    function showError(msg) {
        alertBox.textContent = msg;
        alertBox.classList.remove("d-none");
        window.scrollTo({ top: alertBox.offsetTop - 40, behavior: "smooth" });
    }

    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();

            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            alertBox.classList.add("d-none");
            submitBtn.disabled = true;
            submitLabel.textContent = "SUBMITTING...";

            const formData = new FormData(form);

            fetch("passport_form.php", {
                method: "POST",
                body: formData
            })
            .then(res => res.json().then(data => ({ ok: res.ok, data })))
            .then(({ ok, data }) => {
                if (!ok || !data.success) {
                    throw new Error(data.message || "Something went wrong. Please try again.");
                }
                // Populate + reveal receipt, hide the fillable form
                document.getElementById("receiptRef").textContent    = data.ref_code;
                document.getElementById("receiptStatus").textContent = data.status;
                document.getElementById("receiptDate").textContent   = data.submitted;

                headerTitle.textContent = "APPLICATION RECEIVED";
                headerSub.textContent   = "Reference: " + data.ref_code;

                formBody.classList.add("d-none");
                if (progressRail) progressRail.classList.add("d-none");
                receiptBody.classList.remove("d-none");
            })
            .catch(err => {
                showError(err.message);
                submitBtn.disabled = false;
                submitLabel.textContent = "SUBMIT APPLICATION";
            });
        });
    }

    const newApplicationBtn = document.getElementById("newApplicationBtn");
    if (newApplicationBtn) {
        newApplicationBtn.addEventListener("click", () => window.location.reload());
    }
});
</script>

<style>
.official-form .form-control,
.official-form .form-select {
    border: 2px solid #033754;
    border-radius: 8px;
    transition: box-shadow .15s ease, border-color .15s ease;
}
.official-form .form-control:focus {
    border-color: #89d900;
    box-shadow: 0 0 0 0.2rem rgba(137,217,0,0.25);
}
.form-check-input:checked {
    background-color: #89d900;
    border-color: #89d900;
}
.watermark {
    opacity: 0.08;
    filter: grayscale(100%) contrast(1.15);
}

/* ---- Letterhead ---- */
.top-accent-bar {
    height: 5px;
    background: linear-gradient(90deg, #033754 0%, #10b6d7 55%, #89d900 100%);
}
.letterhead {
    padding: 2rem 2.75rem 1.5rem;
    background: #fff;
}
.letterhead-logo {
    height: 52px;
    width: auto;
}
.brand-name {
    font-weight: 800;
    font-size: 1.05rem;
    color: #033754;
    letter-spacing: .2px;
    line-height: 1.3;
}
.brand-tagline {
    font-size: .7rem;
    font-weight: 700;
    letter-spacing: 1.3px;
    text-transform: uppercase;
    color: #10b6d7;
    margin-top: 2px;
}
.letterhead-contact {
    font-size: .78rem;
    color: #5b6b74;
    line-height: 1.7;
}
.letterhead-contact i { color: #10b6d7; width: 14px; text-align: center; margin-right: 4px; }
.letterhead-divider {
    height: 3px;
    border-radius: 2px;
    margin: 1.5rem 0 1.5rem;
    background: linear-gradient(90deg, #033754 0%, #10b6d7 60%, #89d900 100%);
    opacity: .9;
}
.letterhead-title h1 {
    font-weight: 800;
    font-size: clamp(1.35rem, 3vw, 1.9rem);
    color: #033754;
    letter-spacing: .3px;
    text-transform: uppercase;
    margin: 0 0 .5rem;
    line-height: 1.3;
}
.letterhead-subtitle {
    font-size: .85rem;
    color: #6c7a82;
    letter-spacing: .3px;
    margin: 0;
}

.section-title {
    color:#033754;
    border-bottom: 2px solid #89d900;
    display: inline-flex;
    align-items: center;
    gap: .6rem;
    padding-bottom: 6px;
    font-size: 1.3rem;
}
.step-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #033754;
    color: #fff;
    font-size: .85rem;
    flex-shrink: 0;
}
.progress-rail {
    border-bottom: 1px solid #e9ecef;
    padding-bottom: 1rem;
}
.rail-step {
    font-size: .8rem;
    font-weight: 600;
    color: #9aa5ad;
    cursor: pointer;
    padding: 4px 10px;
    border-radius: 20px;
    transition: background .15s ease, color .15s ease;
}
.rail-step:hover { color: #033754; }
.rail-step.active { background: #033754; color: #fff; }

@media (max-width: 768px) {
    .letterhead { padding: 1.75rem 1.5rem 1.25rem; }
    .letterhead-top { flex-direction: column; text-align: center; }
    .letterhead-top > div:first-child { flex-direction: column; text-align: center; }
    .letterhead-contact { text-align: center !important; }
    .progress-rail { justify-content: center; }
}


</style>