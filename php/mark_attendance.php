<?php
require_once 'includes/auth.php';
require_once 'db.php';

require_role(['teacher', 'admin']);

define('PAGE_TITLE', 'Mark Attendance');

$teacher_id = get_user_id();
$user_role = get_user_role();
$error_msg = '';
$success_msg = '';

$selected_date = $_POST['date'] ?? $_GET['date'] ?? date('Y-m-d');
$selected_subject = trim($_POST['subject'] ?? $_GET['subject'] ?? '');

// Handle Quick Add Subject directly from this screen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_add_subject'])) {
    verify_csrf_token();

    $new_subj_name = trim($_POST['new_subject_name'] ?? '');
    $new_subj_code = strtoupper(trim($_POST['new_subject_code'] ?? ''));
    $new_subj_sem  = trim($_POST['new_subject_sem'] ?? '');

    if (empty($new_subj_name) || empty($new_subj_code)) {
        $error_msg = "Subject name and code are required.";
    } else {
        $dup_chk = $conn->prepare("SELECT id FROM subjects WHERE teacher_id = ? AND subject_code = ?");
        $dup_chk->bind_param("is", $teacher_id, $new_subj_code);
        $dup_chk->execute();
        if ($dup_chk->get_result()->num_rows > 0) {
            $error_msg = "Subject code '$new_subj_code' is already in use.";
        } else {
            $ins = $conn->prepare("INSERT INTO subjects (teacher_id, subject_name, subject_code, class_or_semester) VALUES (?, ?, ?, ?)");
            $ins->bind_param("isss", $teacher_id, $new_subj_name, $new_subj_code, $new_subj_sem);
            if ($ins->execute()) {
                $selected_subject = $new_subj_name;
                $success_msg = "Subject <strong>" . e($new_subj_name) . "</strong> created and selected!";
            } else {
                $error_msg = "Error creating subject: " . $conn->error;
            }
            $ins->close();
        }
        $dup_chk->close();
    }
}

// Fetch active subjects authorized for this teacher
if ($user_role === 'admin') {
    $t_stmt = $conn->prepare("SELECT id, subject_name, subject_code FROM subjects WHERE status = 'active' ORDER BY subject_name ASC");
} else {
    $t_stmt = $conn->prepare("SELECT id, subject_name, subject_code FROM subjects WHERE teacher_id = ? AND status = 'active' ORDER BY subject_name ASC");
    $t_stmt->bind_param("i", $teacher_id);
}
$t_stmt->execute();
$t_res = $t_stmt->get_result();
$registered_subjects = [];
while ($r = $t_res->fetch_assoc()) {
    $registered_subjects[] = $r;
}
$t_stmt->close();

// Select default subject if none selected
if (empty($selected_subject) && !empty($registered_subjects)) {
    $selected_subject = $registered_subjects[0]['subject_name'];
}

// Process Attendance Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    verify_csrf_token();

    $attendance_entries = $_POST['attendance'] ?? [];
    $date = trim($_POST['date'] ?? date('Y-m-d'));
    $subject = trim($_POST['subject'] ?? '');

    if (empty($date)) {
        $error_msg = "Please select an attendance date.";
    } elseif (empty($subject)) {
        $error_msg = "Please select a valid subject for roll call.";
    } elseif (empty($attendance_entries)) {
        $error_msg = "No student attendance records were provided.";
    } else {
        $saved_count = 0;
        $present_count = 0;
        $absent_count = 0;

        foreach ($attendance_entries as $student_id => $status) {
            $student_id = (int)$student_id;
            $status = ($status === 'Absent') ? 'Absent' : 'Present';

            if ($status === 'Present') {
                $present_count++;
            } else {
                $absent_count++;
            }

            // Check if record exists for this student, date, and subject
            $check_stmt = $conn->prepare("SELECT id FROM attendance WHERE student_id = ? AND date = ? AND subject = ?");
            $check_stmt->bind_param("iss", $student_id, $date, $subject);
            $check_stmt->execute();
            $check_res = $check_stmt->get_result();

            if ($check_res && $check_res->num_rows > 0) {
                $existing = $check_res->fetch_assoc();
                $update_stmt = $conn->prepare("UPDATE attendance SET status = ?, teacher_id = ? WHERE id = ?");
                $update_stmt->bind_param("sii", $status, $teacher_id, $existing['id']);
                $update_stmt->execute();
                $update_stmt->close();
            } else {
                $insert_stmt = $conn->prepare("INSERT INTO attendance (student_id, date, subject, status, teacher_id) VALUES (?, ?, ?, ?, ?)");
                $insert_stmt->bind_param("isssi", $student_id, $date, $subject, $status, $teacher_id);
                $insert_stmt->execute();
                $insert_stmt->close();
            }
            $check_stmt->close();
            $saved_count++;
        }

        $selected_date = $date;
        $selected_subject = $subject;
        $success_msg = "Successfully saved attendance for <strong>$saved_count</strong> students in <strong>" . e($subject) . "</strong> on " . e($date) . " (🟢 $present_count Present, 🔴 $absent_count Absent).";
    }
}

// Fetch students list
$students_result = $conn->query("SELECT id, name, roll_number, class FROM students ORDER BY class ASC, roll_number ASC, name ASC");
$students = [];
$distinct_classes = [];
if ($students_result) {
    while ($row = $students_result->fetch_assoc()) {
        $students[] = $row;
        if (!in_array($row['class'], $distinct_classes)) {
            $distinct_classes[] = $row['class'];
        }
    }
}

// Fetch existing attendance records for the selected date and subject
$existing_attendance = [];
if (!empty($selected_subject)) {
    $att_stmt = $conn->prepare("SELECT student_id, status FROM attendance WHERE date = ? AND subject = ?");
    $att_stmt->bind_param("ss", $selected_date, $selected_subject);
    $att_stmt->execute();
    $att_result = $att_stmt->get_result();
    if ($att_result) {
        while ($row = $att_result->fetch_assoc()) {
            $existing_attendance[$row['student_id']] = $row['status'];
        }
    }
    $att_stmt->close();
}

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="content-container">
    <div class="page-header">
        <div>
            <h1 class="page-title">Class Roll Call</h1>
            <p class="page-subtitle">Mark daily student attendance by subject with quick roster toggles.</p>
        </div>
        <div>
            <a href="manage_subjects.php" class="btn btn-secondary">
                📚 Manage Subjects
            </a>
        </div>
    </div>

    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger">
            <span><?php echo $error_msg; ?></span>
        </div>
    <?php endif; ?>

    <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success">
            <span><?php echo $success_msg; ?></span>
        </div>
    <?php endif; ?>

    <!-- Session Setup Card -->
    <div class="card">
        <div class="card-header-clean">
            <h2 class="card-title">Attendance Session Setup</h2>
            <button type="button" class="btn btn-sm btn-secondary" onclick="toggleQuickSubjectModal()">
                + Create Subject
            </button>
        </div>

        <form id="attendanceForm" method="POST" action="mark_attendance.php">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="save_attendance" value="1">

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; align-items: flex-start; margin-bottom: 1.25rem;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="dateInput">📅 Attendance Date</label>
                    <input type="date" id="dateInput" name="date" value="<?php echo e($selected_date); ?>" required onchange="reloadAttendanceSession()">
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label for="subjectInput">📚 Course / Subject</label>
                    <?php if (empty($registered_subjects)): ?>
                        <div style="font-size: 0.85rem; color: var(--danger); margin-top: 4px;">
                            No subjects registered. <a href="manage_subjects.php" style="color: var(--primary); font-weight: 600;">Add a subject first &rarr;</a>
                        </div>
                    <?php else: ?>
                        <select id="subjectInput" name="subject" required onchange="reloadAttendanceSession()">
                            <?php foreach ($registered_subjects as $s): ?>
                                <option value="<?php echo e($s['subject_name']); ?>" <?php echo ($selected_subject === $s['subject_name']) ? 'selected' : ''; ?>>
                                    <?php echo e($s['subject_name']); ?> (<?php echo e($s['subject_code']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <!-- Quick Chips -->
                        <div class="subject-chips-container">
                            <?php foreach ($registered_subjects as $chip): 
                                $isActive = ($selected_subject === $chip['subject_name']);
                            ?>
                                <span class="subject-chip <?php echo $isActive ? 'active' : ''; ?>" onclick="selectSubject('<?php echo e($chip['subject_name']); ?>')">
                                    <?php echo e($chip['subject_name']); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label for="classFilter">🏫 Filter by Class</label>
                    <select id="classFilter" onchange="filterStudentsByClass()">
                        <option value="all">All Classes (<?php echo count($students); ?> students)</option>
                        <?php foreach ($distinct_classes as $cls): ?>
                            <option value="<?php echo e($cls); ?>"><?php echo e($cls); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <?php if (empty($students)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">👥</div>
                    <h3>No Students Enrolled</h3>
                    <p style="margin: 0.5rem 0 1.25rem;">Please register students in the system before recording attendance.</p>
                    <a href="add_student.php" class="btn btn-primary">Enroll Students</a>
                </div>
            <?php else: ?>
                <!-- Roster Action Toolbar -->
                <div class="filter-toolbar" style="margin-bottom: 1rem;">
                    <div style="flex: 1; min-width: 220px;">
                        <input type="text" id="studentSearch" placeholder="🔍 Search student name or roll number..." oninput="filterStudentsBySearch()" style="padding: 0.5rem 0.85rem; font-size: 0.88rem;">
                    </div>

                    <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                        <span class="badge" id="statsPill" style="background: #ffffff; border: 1px solid var(--border-color); font-size: 0.82rem; padding: 0.4rem 0.75rem;">
                            <span id="statTotal"><?php echo count($students); ?> Students</span> &bull; 
                            <span style="color: var(--success);" id="statPresent">0 Present</span> &bull; 
                            <span style="color: var(--danger);" id="statAbsent">0 Absent</span>
                        </span>

                        <button type="button" class="btn btn-sm btn-secondary" onclick="markAll('Present')">
                            🟢 All Present
                        </button>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="markAll('Absent')">
                            🔴 All Absent
                        </button>
                    </div>
                </div>

                <!-- Interactive Student Cards Grid -->
                <div style="display: flex; flex-direction: column; gap: 0.65rem;" id="studentRosterList">
                    <?php foreach ($students as $stu): 
                        $stu_id = $stu['id'];
                        $current_status = $existing_attendance[$stu_id] ?? 'Present';
                        $isPresent = ($current_status === 'Present');

                        $names = explode(' ', trim($stu['name']));
                        $initials = strtoupper(substr($names[0], 0, 1) . (isset($names[1]) ? substr($names[1], 0, 1) : ''));
                    ?>
                        <div class="student-card <?php echo $isPresent ? 'status-present' : 'status-absent'; ?>"
                             id="card_<?php echo $stu_id; ?>"
                             data-student-id="<?php echo $stu_id; ?>"
                             data-name="<?php echo strtolower(e($stu['name'])); ?>"
                             data-roll="<?php echo strtolower(e($stu['roll_number'])); ?>"
                             data-class="<?php echo e($stu['class']); ?>">
                            
                            <div style="display: flex; align-items: center; gap: 0.85rem;">
                                <div class="student-avatar"><?php echo e($initials); ?></div>
                                <div>
                                    <span style="font-weight: 700; font-size: 0.95rem; color: var(--navy-dark);"><?php echo e($stu['name']); ?></span>
                                    <div style="font-size: 0.8rem; color: var(--text-muted); display: flex; gap: 0.5rem; align-items: center; margin-top: 2px;">
                                        <span>Roll: <strong><?php echo e($stu['roll_number']); ?></strong></span>
                                        <span class="student-class-badge"><?php echo e($stu['class']); ?></span>
                                        <?php if (isset($existing_attendance[$stu_id])): ?>
                                            <span style="font-size: 0.72rem; color: #64748b;">(Previously saved)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Interactive Present / Absent Segmented Toggles -->
                            <div class="status-toggle-wrapper">
                                <label class="status-toggle-label <?php echo $isPresent ? 'active-present' : ''; ?>" id="label_present_<?php echo $stu_id; ?>">
                                    <input type="radio" 
                                           name="attendance[<?php echo $stu_id; ?>]" 
                                           value="Present" 
                                           <?php echo $isPresent ? 'checked' : ''; ?>
                                           onchange="updateStudentStatus(<?php echo $stu_id; ?>, 'Present')">
                                    🟢 Present
                                </label>
                                <label class="status-toggle-label <?php echo !$isPresent ? 'active-absent' : ''; ?>" id="label_absent_<?php echo $stu_id; ?>">
                                    <input type="radio" 
                                           name="attendance[<?php echo $stu_id; ?>]" 
                                           value="Absent" 
                                           <?php echo !$isPresent ? 'checked' : ''; ?>
                                           onchange="updateStudentStatus(<?php echo $stu_id; ?>, 'Absent')">
                                    🔴 Absent
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Sticky Bottom Submission Bar -->
                <div style="position: sticky; bottom: 1rem; background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 0.85rem 1.25rem; display: flex; justify-content: space-between; align-items: center; box-shadow: var(--shadow-lg); z-index: 30; margin-top: 1.5rem; gap: 1rem; flex-wrap: wrap;">
                    <div style="font-size: 0.9rem; font-weight: 500; color: var(--navy-dark);">
                        Subject: <strong id="barSubject"><?php echo e($selected_subject); ?></strong> &bull; 
                        <span id="barSummary" style="color: var(--text-muted);"><?php echo count($students); ?> Students in class</span>
                    </div>
                    <div style="display: flex; gap: 0.5rem;">
                        <a href="view_attendance.php" class="btn btn-secondary">Review Records</a>
                        <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.75rem;">
                            💾 Save Attendance
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Quick Add Subject Modal -->
<div id="quickSubjectModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 110; align-items: center; justify-content: center; padding: 1rem;">
    <div class="card" style="max-width: 440px; width: 100%; box-shadow: var(--shadow-lg); margin-bottom: 0;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--border-color);">
            <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--navy-dark); margin: 0;">➕ Add Subject</h3>
            <button type="button" onclick="toggleQuickSubjectModal()" style="background: none; border: none; font-size: 1.3rem; cursor: pointer; color: var(--text-muted);">&times;</button>
        </div>
        <form method="POST" action="mark_attendance.php">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="quick_add_subject" value="1">
            <input type="hidden" name="date" value="<?php echo e($selected_date); ?>">

            <div class="form-group">
                <label for="new_subject_name">Subject Name *</label>
                <input type="text" id="new_subject_name" name="new_subject_name" placeholder="e.g. Operating Systems" required>
            </div>
            <div class="form-group">
                <label for="new_subject_code">Subject Code *</label>
                <input type="text" id="new_subject_code" name="new_subject_code" placeholder="e.g. CS-204" required>
            </div>
            <div class="form-group">
                <label for="new_subject_sem">Class / Semester (Optional)</label>
                <input type="text" id="new_subject_sem" name="new_subject_sem" placeholder="e.g. 4th Sem">
            </div>

            <div style="display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 1rem;">
                <button type="button" onclick="toggleQuickSubjectModal()" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Subject</button>
            </div>
        </form>
    </div>
</div>

<script>
    function toggleQuickSubjectModal() {
        const m = document.getElementById('quickSubjectModal');
        m.style.display = (m.style.display === 'none' || m.style.display === '') ? 'flex' : 'none';
        if (m.style.display === 'flex') {
            document.getElementById('new_subject_name').focus();
        }
    }

    function updateStudentStatus(studentId, status) {
        const card = document.getElementById('card_' + studentId);
        const labelPresent = document.getElementById('label_present_' + studentId);
        const labelAbsent = document.getElementById('label_absent_' + studentId);

        if (status === 'Present') {
            labelPresent.classList.add('active-present');
            labelAbsent.classList.remove('active-absent');
            card.classList.add('status-present');
            card.classList.remove('status-absent');
        } else {
            labelAbsent.classList.add('active-absent');
            labelPresent.classList.remove('active-present');
            card.classList.add('status-absent');
            card.classList.remove('status-present');
        }
        recalculateLiveCounts();
    }

    function markAll(status) {
        const cards = document.querySelectorAll('.student-card');
        cards.forEach(card => {
            if (card.style.display !== 'none') {
                const studentId = card.getAttribute('data-student-id');
                const radio = card.querySelector(`input[type="radio"][value="${status}"]`);
                if (radio) {
                    radio.checked = true;
                    updateStudentStatus(studentId, status);
                }
            }
        });
    }

    function recalculateLiveCounts() {
        const cards = document.querySelectorAll('.student-card');
        let total = 0, p = 0, a = 0;

        cards.forEach(card => {
            if (card.style.display !== 'none') {
                total++;
                const checked = card.querySelector('input[type="radio"]:checked');
                if (checked && checked.value === 'Present') p++;
                else a++;
            }
        });

        document.getElementById('statTotal').innerText = `${total} Students`;
        document.getElementById('statPresent').innerText = `${p} Present`;
        document.getElementById('statAbsent').innerText = `${a} Absent`;
        
        const summary = document.getElementById('barSummary');
        if (summary) {
            summary.innerText = `${p} Present, ${a} Absent (${total} Total)`;
        }
    }

    function filterStudentsBySearch() {
        const q = document.getElementById('studentSearch').value.toLowerCase().trim();
        const classFilter = document.getElementById('classFilter').value;
        const cards = document.querySelectorAll('.student-card');

        cards.forEach(card => {
            const name = card.getAttribute('data-name');
            const roll = card.getAttribute('data-roll');
            const cls  = card.getAttribute('data-class');

            const matchesSearch = !q || name.includes(q) || roll.includes(q);
            const matchesClass  = (classFilter === 'all') || (cls === classFilter);

            card.style.display = (matchesSearch && matchesClass) ? 'flex' : 'none';
        });

        recalculateLiveCounts();
    }

    function filterStudentsByClass() {
        filterStudentsBySearch();
    }

    function selectSubject(name) {
        const sel = document.getElementById('subjectInput');
        sel.value = name;
        reloadAttendanceSession();
    }

    function reloadAttendanceSession() {
        const date = document.getElementById('dateInput').value;
        const subject = document.getElementById('subjectInput').value;
        if (date) {
            window.location.href = `mark_attendance.php?date=${encodeURIComponent(date)}&subject=${encodeURIComponent(subject)}`;
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        recalculateLiveCounts();
    });
</script>

<?php require_once 'includes/footer.php'; ?>
