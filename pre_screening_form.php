<?php
require_once __DIR__ . '/includes/db.php';

/* =====================================================================
 * AJAX SUBMISSION HANDLER
 * When the form is submitted, JS posts back to THIS SAME FILE with
 * ajax_submit=1. We validate, assemble the JSON columns (medical
 * history, education, former employers, references), generate a
 * unique ref code, insert into `pre_screening_applications`, and
 * return JSON — no redirect, no separate page.
 *
 * NOTE: Assessment/interview fields (interviewed_by, interview_date,
 * remark, ratings, final_result, approved_by) are NOT part of this
 * public form. Those are filled in later by staff from the admin
 * services dashboard.
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

    function opt_field(string $name): ?string
    {
        if (!isset($_POST[$name]) || trim($_POST[$name]) === '') {
            return null;
        }
        return trim($_POST[$name]);
    }

    // ---- Personal information ----
    $last_name    = req_field('last_name');
    $first_name   = req_field('first_name');
    $middle_name  = opt_field('middle_name');
    $dob          = req_field('dob');
    $sex          = req_field('sex');
    $phone        = req_field('phone');
    $work_phone   = opt_field('work_phone');
    $email        = req_field('email');
    $ghana_card   = req_field('ghana_card');
    $disability   = req_field('disability');
    $criminal_record = req_field('criminal_record');

    if (!in_array($sex, ['Male', 'Female'], true)) {
        json_fail("Please select a valid sex.");
    }
    if (!in_array($disability, ['Yes', 'No'], true)) {
        json_fail("Please answer the disability question.");
    }
    if (!in_array($criminal_record, ['Yes', 'No'], true)) {
        json_fail("Please answer the criminal record question.");
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_fail("Please provide a valid email address.");
    }

    // ---- Address ----
    $address = req_field('address');
    $city    = req_field('city');
    $country = req_field('country');
    $gps     = opt_field('gps');

    // ---- Emergency contact ----
    $emergency_name          = req_field('emergency_name');
    $emergency_relationship  = req_field('emergency_relationship');
    $emergency_address       = opt_field('emergency_address');
    $emergency_city          = opt_field('emergency_city');
    $emergency_country       = opt_field('emergency_country');
    $emergency_home_phone    = opt_field('emergency_home_phone');
    $emergency_work_phone    = opt_field('emergency_work_phone');
    $emergency_email         = opt_field('emergency_email');
    $emergency_ghana_card    = opt_field('emergency_ghana_card');
    $emergency_gps           = opt_field('emergency_gps');

    if ($emergency_email !== null && !filter_var($emergency_email, FILTER_VALIDATE_EMAIL)) {
        json_fail("Please provide a valid emergency contact email, or leave it blank.");
    }

    // ---- Medical history (conditional JSON) ----
    $has_medical_condition = $_POST['has_medical_condition'] ?? 'No';
    $medical_history = null;
    if ($has_medical_condition === 'Yes') {
        $conditions = [];
        $condNames  = $_POST['medical_condition'] ?? [];
        $condDetails = $_POST['medical_details'] ?? [];
        foreach ($condNames as $i => $cond) {
            $cond = trim($cond);
            $details = trim($condDetails[$i] ?? '');
            if ($cond === '' && $details === '') continue;
            if ($cond === '') json_fail("Please name each medical condition you list.");
            $conditions[] = ['condition' => $cond, 'details' => $details];
        }
        if (empty($conditions)) {
            json_fail("You indicated a medical condition — please provide at least one, or select 'No'.");
        }
        $medical_history = json_encode($conditions);
    }

    // ---- Education history (required JSON, at least 1 entry) ----
    $education = [];
    $eduSchool = $_POST['edu_school'] ?? [];
    $eduQual   = $_POST['edu_qualification'] ?? [];
    $eduYear   = $_POST['edu_year'] ?? [];
    foreach ($eduSchool as $i => $school) {
        $school = trim($school);
        $qual   = trim($eduQual[$i] ?? '');
        $year   = trim($eduYear[$i] ?? '');
        if ($school === '' && $qual === '' && $year === '') continue;
        if ($school === '') json_fail("Each education entry needs a school/institution name.");
        $education[] = ['school' => $school, 'qualification' => $qual, 'year' => $year];
    }
    if (empty($education)) {
        json_fail("Please add at least one education history entry.");
    }
    $education_history = json_encode($education);

    // ---- Former employers (optional JSON) ----
    $former = [];
    $empName     = $_POST['emp_name'] ?? [];
    $empPosition = $_POST['emp_position'] ?? [];
    $empDuration = $_POST['emp_duration'] ?? [];
    $empReason   = $_POST['emp_reason'] ?? [];
    foreach ($empName as $i => $name) {
        $name     = trim($name);
        $position = trim($empPosition[$i] ?? '');
        $duration = trim($empDuration[$i] ?? '');
        $reason   = trim($empReason[$i] ?? '');
        if ($name === '' && $position === '' && $duration === '' && $reason === '') continue;
        if ($name === '') json_fail("Each former employer entry needs an employer name.");
        $former[] = ['employer' => $name, 'position' => $position, 'duration' => $duration, 'reason_for_leaving' => $reason];
    }
    $former_employers = empty($former) ? null : json_encode($former);

    // ---- References (required JSON, at least 2 entries) ----
    $refs = [];
    $refName  = $_POST['ref_name'] ?? [];
    $refRel   = $_POST['ref_relationship'] ?? [];
    $refPhone = $_POST['ref_phone'] ?? [];
    $refAddr  = $_POST['ref_address'] ?? [];
    foreach ($refName as $i => $name) {
        $name  = trim($name);
        $rel   = trim($refRel[$i] ?? '');
        $phone2 = trim($refPhone[$i] ?? '');
        $addr  = trim($refAddr[$i] ?? '');
        if ($name === '' && $rel === '' && $phone2 === '' && $addr === '') continue;
        if ($name === '' || $phone2 === '') json_fail("Each reference needs at least a name and phone number.");
        $refs[] = ['name' => $name, 'relationship' => $rel, 'phone' => $phone2, 'address' => $addr];
    }
    if (count($refs) < 2) {
        json_fail("Please provide at least 2 references.");
    }
    $references = json_encode($refs);

    // ---- Position, authorization, signature ----
    $auth_name        = opt_field('auth_name');
    $position_applied = req_field('position_applied');
    $signature        = req_field('signature');

    $declaration_accepted = isset($_POST['declaration']) ? 1 : 0;
    if (!$declaration_accepted) {
        json_fail("You must accept the declaration before submitting.");
    }

    if (!DateTime::createFromFormat('Y-m-d', $dob)) {
        json_fail("Date of birth is invalid.");
    }

    // ---- Unique ref code ----
    function generate_ref_code(PDO $pdo): string
    {
        $check = $pdo->prepare("SELECT id FROM pre_screening_applications WHERE ref_code = ? LIMIT 1");
        do {
            $ref = 'BTC-PS-' . date('Y') . '-' . random_int(100000, 999999);
            $check->execute([$ref]);
        } while ($check->fetch());
        return $ref;
    }
    $ref_code = generate_ref_code($pdo);

    // ---- Insert into DB ----
    try {
        $stmt = $pdo->prepare("
            INSERT INTO pre_screening_applications (
                ref_code, last_name, first_name, middle_name,
                address, city, country, phone, work_phone, email, dob, sex,
                disability, ghana_card, gps, criminal_record,
                emergency_name, emergency_relationship, emergency_address, emergency_city,
                emergency_country, emergency_home_phone, emergency_work_phone, emergency_email,
                emergency_ghana_card, emergency_gps,
                medical_history, education_history, former_employers, `references`,
                auth_name, position_applied, signature
            ) VALUES (
                :ref_code, :last_name, :first_name, :middle_name,
                :address, :city, :country, :phone, :work_phone, :email, :dob, :sex,
                :disability, :ghana_card, :gps, :criminal_record,
                :emergency_name, :emergency_relationship, :emergency_address, :emergency_city,
                :emergency_country, :emergency_home_phone, :emergency_work_phone, :emergency_email,
                :emergency_ghana_card, :emergency_gps,
                :medical_history, :education_history, :former_employers, :references,
                :auth_name, :position_applied, :signature
            )
        ");
        $stmt->execute([
            ':ref_code'                => $ref_code,
            ':last_name'               => $last_name,
            ':first_name'              => $first_name,
            ':middle_name'             => $middle_name,
            ':address'                 => $address,
            ':city'                    => $city,
            ':country'                 => $country,
            ':phone'                   => $phone,
            ':work_phone'              => $work_phone,
            ':email'                   => $email,
            ':dob'                     => $dob,
            ':sex'                     => $sex,
            ':disability'              => $disability,
            ':ghana_card'              => $ghana_card,
            ':gps'                     => $gps,
            ':criminal_record'         => $criminal_record,
            ':emergency_name'          => $emergency_name,
            ':emergency_relationship'  => $emergency_relationship,
            ':emergency_address'       => $emergency_address,
            ':emergency_city'          => $emergency_city,
            ':emergency_country'       => $emergency_country,
            ':emergency_home_phone'    => $emergency_home_phone,
            ':emergency_work_phone'    => $emergency_work_phone,
            ':emergency_email'         => $emergency_email,
            ':emergency_ghana_card'    => $emergency_ghana_card,
            ':emergency_gps'           => $emergency_gps,
            ':medical_history'         => $medical_history,
            ':education_history'       => $education_history,
            ':former_employers'        => $former_employers,
            ':references'              => $references,
            ':auth_name'               => $auth_name,
            ':position_applied'        => $position_applied,
            ':signature'               => $signature,
        ]);
    } catch (PDOException $e) {
        error_log("Pre-screening application insert failed: " . $e->getMessage());
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

<!-- OFFICIAL PRE-SCREENING / EMPLOYMENT APPLICATION FORM -->
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
                                    <div class="brand-tagline">Applicant Pre-Screening &amp; Vetting</div>
                                </div>
                            </div>
                            <div class="letterhead-contact text-md-end no-print">
                                <div><i class="fas fa-location-dot"></i> Tema Golf City &middot; Kumasi Ahodwo &middot; Kasoa Adam Nana</div>
                                <div><i class="fas fa-phone"></i> +233 596 204 991</div>
                            </div>
                        </div>

                        <div class="letterhead-divider"></div>

                        <div class="letterhead-title text-center">
                            <h1 id="headerTitle">Pre-Screening Application Form</h1>
                            <p class="letterhead-subtitle" id="headerSubtitle">Applicant Vetting &amp; Background Verification</p>
                        </div>
                    </div>

                    <!-- Progress rail -->
                    <div class="px-5 pt-4 no-print" id="progressRail">
                        <div class="progress-rail d-flex justify-content-between flex-wrap gap-2">
                            <span class="rail-step active" data-target="sec-1">1. Personal</span>
                            <span class="rail-step" data-target="sec-2">2. Address</span>
                            <span class="rail-step" data-target="sec-3">3. Emergency</span>
                            <span class="rail-step" data-target="sec-4">4. Medical</span>
                            <span class="rail-step" data-target="sec-5">5. Education</span>
                            <span class="rail-step" data-target="sec-6">6. Employment</span>
                            <span class="rail-step" data-target="sec-7">7. References</span>
                            <span class="rail-step" data-target="sec-8">8. Confirm</span>
                        </div>
                    </div>

                    <!-- Inline alert for AJAX errors -->
                    <div class="px-5 pt-4">
                        <div class="alert alert-danger d-none no-print" id="formAlert" role="alert"></div>
                    </div>

                    <!-- FORM BODY -->
                    <div class="p-5 p-xl-7 position-relative z-2" id="formBody">

                        <form method="POST" action="pre_screening_form.php" class="official-form" id="preScreenForm">
                            <input type="hidden" name="ajax_submit" value="1">

                            <!-- Reference Number preview -->
                            <div class="text-end mb-4 no-print">
                                <small class="text-muted">Ref: BTC/PRE-SCREEN/2026/</small>
                                <strong class="text-primary fs-5" id="ref-number">AUTO-GENERATED</strong>
                            </div>

                            <!-- SECTION 1: Personal Information -->
                            <div class="mb-5" id="sec-1">
                                <h5 class="fw-bold mb-3 section-title">
                                    <span class="step-badge">1</span> Personal Information
                                </h5>
                                <div class="row g-4">
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold text-muted">Last Name</label>
                                        <input type="text" name="last_name" class="form-control form-control-lg" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold text-muted">First Name</label>
                                        <input type="text" name="first_name" class="form-control form-control-lg" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold text-muted">Middle Name</label>
                                        <input type="text" name="middle_name" class="form-control form-control-lg">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold text-muted">Date of Birth</label>
                                        <input type="date" name="dob" class="form-control form-control-lg" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold text-muted">Sex</label>
                                        <select name="sex" class="form-select form-select-lg" required>
                                            <option value="">Select</option>
                                            <option value="Male">Male</option>
                                            <option value="Female">Female</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold text-muted">Ghana Card Number</label>
                                        <input type="text" name="ghana_card" class="form-control form-control-lg" required placeholder="GHA-XXXXXXXXX-X">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold text-muted">Primary Phone</label>
                                        <input type="tel" name="phone" class="form-control form-control-lg" required placeholder="+233 ...">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold text-muted">Work Phone (Optional)</label>
                                        <input type="tel" name="work_phone" class="form-control form-control-lg">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold text-muted">Email Address</label>
                                        <input type="email" name="email" class="form-control form-control-lg" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold text-muted">Do you have any disability?</label>
                                        <select name="disability" class="form-select form-select-lg" required>
                                            <option value="">Select</option>
                                            <option value="No">No</option>
                                            <option value="Yes">Yes</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold text-muted">Do you have any criminal record?</label>
                                        <select name="criminal_record" class="form-select form-select-lg" required>
                                            <option value="">Select</option>
                                            <option value="No">No</option>
                                            <option value="Yes">Yes</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- SECTION 2: Address -->
                            <div class="mb-5" id="sec-2">
                                <h5 class="fw-bold mb-3 section-title">
                                    <span class="step-badge">2</span> Residential Address
                                </h5>
                                <div class="row g-4">
                                    <div class="col-12">
                                        <label class="form-label fw-semibold text-muted">Address</label>
                                        <textarea name="address" rows="2" class="form-control form-control-lg" required></textarea>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold text-muted">City/Town</label>
                                        <input type="text" name="city" class="form-control form-control-lg" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold text-muted">Country</label>
                                        <input type="text" name="country" class="form-control form-control-lg" required value="Ghana">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold text-muted">GPS Address (Optional)</label>
                                        <input type="text" name="gps" class="form-control form-control-lg" placeholder="e.g., GA-123-4567">
                                    </div>
                                </div>
                            </div>

                            <!-- SECTION 3: Emergency Contact -->
                            <div class="mb-5" id="sec-3">
                                <h5 class="fw-bold mb-3 section-title">
                                    <span class="step-badge">3</span> Emergency Contact
                                </h5>
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold text-muted">Full Name</label>
                                        <input type="text" name="emergency_name" class="form-control form-control-lg" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold text-muted">Relationship</label>
                                        <input type="text" name="emergency_relationship" class="form-control form-control-lg" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-semibold text-muted">Address (Optional)</label>
                                        <textarea name="emergency_address" rows="2" class="form-control form-control-lg"></textarea>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold text-muted">City (Optional)</label>
                                        <input type="text" name="emergency_city" class="form-control form-control-lg">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold text-muted">Country (Optional)</label>
                                        <input type="text" name="emergency_country" class="form-control form-control-lg" value="Ghana">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold text-muted">Ghana Card Number (Optional)</label>
                                        <input type="text" name="emergency_ghana_card" class="form-control form-control-lg">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold text-muted">Home Phone (Optional)</label>
                                        <input type="tel" name="emergency_home_phone" class="form-control form-control-lg">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold text-muted">Work Phone (Optional)</label>
                                        <input type="tel" name="emergency_work_phone" class="form-control form-control-lg">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold text-muted">Email (Optional)</label>
                                        <input type="email" name="emergency_email" class="form-control form-control-lg">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold text-muted">GPS Address (Optional)</label>
                                        <input type="text" name="emergency_gps" class="form-control form-control-lg">
                                    </div>
                                </div>
                            </div>

                            <!-- SECTION 4: Medical History -->
                            <div class="mb-5" id="sec-4">
                                <h5 class="fw-bold mb-3 section-title">
                                    <span class="step-badge">4</span> Medical History
                                </h5>
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold text-muted">Do you have any medical condition or take regular medication?</label>
                                        <select name="has_medical_condition" id="hasMedicalCondition" class="form-select form-select-lg">
                                            <option value="No">No</option>
                                            <option value="Yes">Yes</option>
                                        </select>
                                    </div>
                                </div>
                                <div id="medicalRows" class="mt-4 d-none">
                                    <div class="repeat-row row g-3 align-items-end mb-3">
                                        <div class="col-md-5">
                                            <label class="form-label small fw-semibold text-muted">Condition</label>
                                            <input type="text" name="medical_condition[]" class="form-control">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold text-muted">Details / Medication</label>
                                            <input type="text" name="medical_details[]" class="form-control">
                                        </div>
                                        <div class="col-md-1">
                                            <button type="button" class="btn btn-outline-danger remove-row-btn" title="Remove"><i class="fas fa-trash"></i></button>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-outline-primary btn-sm rounded-pill" data-add="medicalRows" data-template="medicalTemplate">
                                        <i class="fas fa-plus me-1"></i> Add Another Condition
                                    </button>
                                </div>
                            </div>

                            <!-- SECTION 5: Education History -->
                            <div class="mb-5" id="sec-5">
                                <h5 class="fw-bold mb-3 section-title">
                                    <span class="step-badge">5</span> Education History
                                </h5>
                                <div id="eduRows">
                                    <div class="repeat-row row g-3 align-items-end mb-3">
                                        <div class="col-md-5">
                                            <label class="form-label small fw-semibold text-muted">School / Institution</label>
                                            <input type="text" name="edu_school[]" class="form-control" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-semibold text-muted">Qualification</label>
                                            <input type="text" name="edu_qualification[]" class="form-control">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small fw-semibold text-muted">Year</label>
                                            <input type="text" name="edu_year[]" class="form-control" placeholder="YYYY">
                                        </div>
                                        <div class="col-md-1">
                                            <button type="button" class="btn btn-outline-danger remove-row-btn" title="Remove"><i class="fas fa-trash"></i></button>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-outline-primary btn-sm rounded-pill" data-add="eduRows" data-template="eduTemplate">
                                    <i class="fas fa-plus me-1"></i> Add Another School
                                </button>
                            </div>

                            <!-- SECTION 6: Employment History -->
                            <div class="mb-5" id="sec-6">
                                <h5 class="fw-bold mb-3 section-title">
                                    <span class="step-badge">6</span> Former Employers <span class="fs-6 fw-normal text-muted">(Optional)</span>
                                </h5>
                                <div id="empRows">
                                    <div class="repeat-row row g-3 align-items-end mb-3">
                                        <div class="col-md-3">
                                            <label class="form-label small fw-semibold text-muted">Employer</label>
                                            <input type="text" name="emp_name[]" class="form-control">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small fw-semibold text-muted">Position</label>
                                            <input type="text" name="emp_position[]" class="form-control">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small fw-semibold text-muted">Duration</label>
                                            <input type="text" name="emp_duration[]" class="form-control" placeholder="e.g., 2 years">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small fw-semibold text-muted">Reason for Leaving</label>
                                            <input type="text" name="emp_reason[]" class="form-control">
                                        </div>
                                        <div class="col-md-1">
                                            <button type="button" class="btn btn-outline-danger remove-row-btn" title="Remove"><i class="fas fa-trash"></i></button>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-outline-primary btn-sm rounded-pill" data-add="empRows" data-template="empTemplate">
                                    <i class="fas fa-plus me-1"></i> Add Another Employer
                                </button>
                            </div>

                            <!-- SECTION 7: References -->
                            <div class="mb-5" id="sec-7">
                                <h5 class="fw-bold mb-3 section-title">
                                    <span class="step-badge">7</span> References <span class="fs-6 fw-normal text-muted">(At least 2 required)</span>
                                </h5>
                                <div id="refRows">
                                    <div class="repeat-row row g-3 align-items-end mb-3">
                                        <div class="col-md-3">
                                            <label class="form-label small fw-semibold text-muted">Full Name</label>
                                            <input type="text" name="ref_name[]" class="form-control" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small fw-semibold text-muted">Relationship</label>
                                            <input type="text" name="ref_relationship[]" class="form-control">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small fw-semibold text-muted">Phone</label>
                                            <input type="tel" name="ref_phone[]" class="form-control" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small fw-semibold text-muted">Address</label>
                                            <input type="text" name="ref_address[]" class="form-control">
                                        </div>
                                        <div class="col-md-1">
                                            <button type="button" class="btn btn-outline-danger remove-row-btn" title="Remove"><i class="fas fa-trash"></i></button>
                                        </div>
                                    </div>
                                    <div class="repeat-row row g-3 align-items-end mb-3">
                                        <div class="col-md-3">
                                            <label class="form-label small fw-semibold text-muted">Full Name</label>
                                            <input type="text" name="ref_name[]" class="form-control" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small fw-semibold text-muted">Relationship</label>
                                            <input type="text" name="ref_relationship[]" class="form-control">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small fw-semibold text-muted">Phone</label>
                                            <input type="tel" name="ref_phone[]" class="form-control" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small fw-semibold text-muted">Address</label>
                                            <input type="text" name="ref_address[]" class="form-control">
                                        </div>
                                        <div class="col-md-1">
                                            <button type="button" class="btn btn-outline-danger remove-row-btn" title="Remove"><i class="fas fa-trash"></i></button>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-outline-primary btn-sm rounded-pill" data-add="refRows" data-template="refTemplate">
                                    <i class="fas fa-plus me-1"></i> Add Another Reference
                                </button>
                            </div>

                            <!-- SECTION 8: Position & Declaration -->
                            <div class="mb-5" id="sec-8">
                                <h5 class="fw-bold mb-3 section-title">
                                    <span class="step-badge">8</span> Position Applied For &amp; Declaration
                                </h5>
                                <div class="row g-4 mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold text-muted">Position Applied For</label>
                                        <input type="text" name="position_applied" class="form-control form-control-lg" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold text-muted">Referred By / Authorized By (Optional)</label>
                                        <input type="text" name="auth_name" class="form-control form-control-lg">
                                    </div>
                                </div>

                                <div class="bg-light p-4 rounded-4 border" style="border-color: #10b6d7;">
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" name="declaration" required>
                                        <label class="form-check-label fw-semibold">I declare that all information provided in this application is true and correct to the best of my knowledge, and I authorize Believe Travel Consult to verify this information.</label>
                                    </div>
                                    <div class="mt-3">
                                        <label class="form-label fw-semibold text-muted">Type your full name to sign this declaration</label>
                                        <input type="text" name="signature" class="form-control form-control-lg" required placeholder="Your full legal name">
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
                <a href="https://wa.me/233596204991?text=Hi! I just submitted my Pre-Screening Application Form."
                   class="btn btn-success btn-lg rounded-pill shadow-lg position-fixed bottom-0 end-0 m-4 z-1050 no-print"
                   style="background:#89d900; color:#033754;" id="whatsappBtn">
                    <i class="fab fa-whatsapp fa-2x me-2"></i> Chat Now
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Row templates (used by JS to add repeatable rows) -->
<template id="medicalTemplate">
    <div class="repeat-row row g-3 align-items-end mb-3">
        <div class="col-md-5">
            <label class="form-label small fw-semibold text-muted">Condition</label>
            <input type="text" name="medical_condition[]" class="form-control">
        </div>
        <div class="col-md-6">
            <label class="form-label small fw-semibold text-muted">Details / Medication</label>
            <input type="text" name="medical_details[]" class="form-control">
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-outline-danger remove-row-btn" title="Remove"><i class="fas fa-trash"></i></button>
        </div>
    </div>
</template>

<template id="eduTemplate">
    <div class="repeat-row row g-3 align-items-end mb-3">
        <div class="col-md-5">
            <label class="form-label small fw-semibold text-muted">School / Institution</label>
            <input type="text" name="edu_school[]" class="form-control">
        </div>
        <div class="col-md-4">
            <label class="form-label small fw-semibold text-muted">Qualification</label>
            <input type="text" name="edu_qualification[]" class="form-control">
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted">Year</label>
            <input type="text" name="edu_year[]" class="form-control" placeholder="YYYY">
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-outline-danger remove-row-btn" title="Remove"><i class="fas fa-trash"></i></button>
        </div>
    </div>
</template>

<template id="empTemplate">
    <div class="repeat-row row g-3 align-items-end mb-3">
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">Employer</label>
            <input type="text" name="emp_name[]" class="form-control">
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">Position</label>
            <input type="text" name="emp_position[]" class="form-control">
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted">Duration</label>
            <input type="text" name="emp_duration[]" class="form-control" placeholder="e.g., 2 years">
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">Reason for Leaving</label>
            <input type="text" name="emp_reason[]" class="form-control">
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-outline-danger remove-row-btn" title="Remove"><i class="fas fa-trash"></i></button>
        </div>
    </div>
</template>

<template id="refTemplate">
    <div class="repeat-row row g-3 align-items-end mb-3">
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">Full Name</label>
            <input type="text" name="ref_name[]" class="form-control">
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">Relationship</label>
            <input type="text" name="ref_relationship[]" class="form-control">
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">Phone</label>
            <input type="tel" name="ref_phone[]" class="form-control">
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted">Address</label>
            <input type="text" name="ref_address[]" class="form-control">
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-outline-danger remove-row-btn" title="Remove"><i class="fas fa-trash"></i></button>
        </div>
    </div>
</template>

<?php include 'includes/footer.php'; ?>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Cosmetic reference preview only, shown until the real save happens
    const ref = "BTC-PS-" + new Date().getFullYear() + "-" + Math.floor(100000 + Math.random() * 900000);
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

    // Toggle medical condition rows
    const medSelect = document.getElementById("hasMedicalCondition");
    const medRows    = document.getElementById("medicalRows");
    if (medSelect && medRows) {
        medSelect.addEventListener("change", function() {
            medRows.classList.toggle("d-none", this.value !== "Yes");
        });
    }

    // Generic "Add Another" row handler using <template> elements
    document.querySelectorAll("[data-add]").forEach(btn => {
        btn.addEventListener("click", function() {
            const containerId = this.dataset.add;
            const templateId  = this.dataset.template;
            const container   = document.getElementById(containerId);
            const template    = document.getElementById(templateId);
            if (!container || !template) return;
            const clone = template.content.cloneNode(true);
            container.appendChild(clone);
        });
    });

    // Delegate remove-row clicks (covers dynamically added rows too)
    document.addEventListener("click", function(e) {
        const removeBtn = e.target.closest(".remove-row-btn");
        if (!removeBtn) return;
        const row = removeBtn.closest(".repeat-row");
        const container = row ? row.parentElement : null;
        // Keep at least one row in each repeatable section
        if (row && container && container.querySelectorAll(".repeat-row").length > 1) {
            row.remove();
        } else if (row) {
            // Clear values instead of removing the last remaining row
            row.querySelectorAll("input").forEach(input => input.value = "");
        }
    });

    // AJAX submit — saves straight to the database, no page reload/redirect
    const form        = document.getElementById("preScreenForm");
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

            // Require at least 2 references filled
            const refNameInputs = form.querySelectorAll('input[name="ref_name[]"]');
            const filledRefs = Array.from(refNameInputs).filter(i => i.value.trim() !== "");
            if (filledRefs.length < 2) {
                showError("Please provide at least 2 references.");
                return;
            }

            alertBox.classList.add("d-none");
            submitBtn.disabled = true;
            submitLabel.textContent = "SUBMITTING...";

            const formData = new FormData(form);

            fetch("pre_screening_form.php", {
                method: "POST",
                body: formData
            })
            .then(res => res.json().then(data => ({ ok: res.ok, data })))
            .then(({ ok, data }) => {
                if (!ok || !data.success) {
                    throw new Error(data.message || "Something went wrong. Please try again.");
                }
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
.official-form .form-control:focus,
.official-form .form-select:focus {
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
    font-size: clamp(1.3rem, 2.8vw, 1.85rem);
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

.repeat-row {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 1rem 1rem 0.25rem;
    margin-left: 0;
    margin-right: 0;
}
.remove-row-btn {
    border-radius: 8px;
}

@media (max-width: 768px) {
    .letterhead { padding: 1.75rem 1.5rem 1.25rem; }
    .letterhead-top { flex-direction: column; text-align: center; }
    .letterhead-top > div:first-child { flex-direction: column; text-align: center; }
    .letterhead-contact { text-align: center !important; }
    .progress-rail { justify-content: center; }
}
</style>