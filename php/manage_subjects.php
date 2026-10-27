<?php
require_once 'includes/auth.php';
require_once 'db.php';

require_role(['teacher', 'admin']);

$teacher_id = get_user_id();
$user_role = get_user_role();
$error_msg = '';
$success_msg = '';

define('PAGE_TITLE', 'Manage Subjects');

// Handle Deletion or Archival
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $subject_id = (int)$_GET['id'];

    // Verify ownership (unless admin)
    if ($user_role === 'admin') {
        $chk = $conn->prepare("SELECT id, subject_name, status FROM subjects WHERE id = ?");
        $chk->bind_param("i", $subject_id);
    } else {
        $chk = $conn->prepare("SELECT id, subject_name, status FROM subjects WHERE id = ? AND teacher_id = ?");
        $chk->bind_param("ii", $subject_id, $teacher_id);
    }
    $chk->execute();
    $sub_data = $chk->get_result()->fetch_assoc();
    $chk->close();

    if ($sub_data) {
        // Check for existing attendance records
        $cnt_stmt = $conn->prepare("SELECT COUNT(*) as total FROM attendance WHERE subject = ?");
        $cnt_stmt->bind_param("s", $sub_data['subject_name']);
        $cnt_stmt->execute();
        $records_count = (int)($cnt_stmt->get_result()->fetch_assoc()['total'] ?? 0);
        $cnt_stmt->close();

        if ($action === 'delete') {
            if ($records_count > 0) {
                // Safe archival instead of destructive deletion
                $arch_stmt = $conn->prepare("UPDATE subjects SET status = 'archived' WHERE id = ?");
                $arch_stmt->bind_param("i", $subject_id);
                $arch_stmt->execute();
                $arch_stmt->close();
                $success_msg = "Subject <strong>" . e($sub_data['subject_name']) . "</strong> has $records_count attendance logs. To protect your data, it was safely <strong>archived</strong> rather than permanently deleted.";
            } else {
                // Safe hard delete when zero dependent records exist
                $del_stmt = $conn->prepare("DELETE FROM subjects WHERE id = ?");
                $del_stmt->bind_param("i", $subject_id);
                $del_stmt->execute();
                $del_stmt->close();
                $success_msg = "Subject <strong>" . e($sub_data['subject_name']) . "</strong> was permanently deleted.";
            }
        } elseif ($action === 'unarchive') {
            $unarch_stmt = $conn->prepare("UPDATE subjects SET status = 'active' WHERE id = ?");
            $unarch_stmt->bind_param("i", $subject_id);
            $unarch_stmt->execute();
            $unarch_stmt->close();
            $success_msg = "Subject <strong>" . e($sub_data['subject_name']) . "</strong> is now active.";
        }
    } else {
        $error_msg = "Subject not found or you do not have permission to modify it.";
    }
}

// Handle Add / Edit Subject Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $form_action = $_POST['form_action'] ?? 'add';
    $subject_name = trim($_POST['subject_name'] ?? '');
    $subject_code = strtoupper(trim($_POST['subject_code'] ?? ''));
    $class_or_semester = trim($_POST['class_or_semester'] ?? '');
    $academic_year = trim($_POST['academic_year'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($subject_name) || empty($subject_code)) {
        $error_msg = "Subject Name and Subject Code are required fields.";
    } else {
        if ($form_action === 'edit' && isset($_POST['subject_id'])) {
            $subject_id = (int)$_POST['subject_id'];
            
            // Check for duplicate code (excluding current subject)
            $dup_chk = $conn->prepare("SELECT id FROM subjects WHERE teacher_id = ? AND subject_code = ? AND id != ?");
            $dup_chk->bind_param("isi", $teacher_id, $subject_code, $subject_id);
            $dup_chk->execute();
            if ($dup_chk->get_result()->num_rows > 0) {
                $error_msg = "Subject code '$subject_code' is already in use by another of your subjects.";
            } else {
                $upd = $conn->prepare("UPDATE subjects SET subject_name = ?, subject_code = ?, class_or_semester = ?, academic_year = ?, description = ? WHERE id = ? AND teacher_id = ?");
                $upd->bind_param("sssssii", $subject_name, $subject_code, $class_or_semester, $academic_year, $description, $subject_id, $teacher_id);
                if ($upd->execute()) {
                    $success_msg = "Subject <strong>" . e($subject_name) . "</strong> updated successfully!";
                } else {
                    $error_msg = "Failed to update subject: " . $conn->error;
                }
                $upd->close();
            }
            $dup_chk->close();
        } else {
            // Check for duplicate subject code for this teacher
            $dup_chk = $conn->prepare("SELECT id FROM subjects WHERE teacher_id = ? AND subject_code = ?");
            $dup_chk->bind_param("is", $teacher_id, $subject_code);
            $dup_chk->execute();
            if ($dup_chk->get_result()->num_rows > 0) {
                $error_msg = "Subject code '$subject_code' is already registered in your courses. Please use a unique code.";
            } else {
                $ins = $conn->prepare("INSERT INTO subjects (teacher_id, subject_name, subject_code, description, class_or_semester, academic_year) VALUES (?, ?, ?, ?, ?, ?)");
                $ins->bind_param("isssss", $teacher_id, $subject_name, $subject_code, $description, $class_or_semester, $academic_year);
                if ($ins->execute()) {
                    $success_msg = "Subject <strong>" . e($subject_name) . "</strong> ($subject_code) created successfully!";
                    $_POST = []; // reset fields
                } else {
                    $error_msg = "Database error while creating subject: " . $conn->error;
                }
                $ins->close();
            }
            $dup_chk->close();
        }
    }
}

// Fetch all subjects for this teacher (or all if admin)
if ($user_role === 'admin') {
    $query = "SELECT s.*, t.username as instructor_name,
              (SELECT COUNT(*) FROM attendance a WHERE a.subject = s.subject_name) as attendance_count
              FROM subjects s 
              JOIN teachers t ON s.teacher_id = t.id 
              ORDER BY s.status ASC, s.created_at DESC";
    $stmt = $conn->prepare($query);
} else {
    $query = "SELECT s.*,
              (SELECT COUNT(*) FROM attendance a WHERE a.teacher_id = s.teacher_id AND a.subject = s.subject_name) as attendance_count
              FROM subjects s 
              WHERE s.teacher_id = ? 
              ORDER BY s.status ASC, s.created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $teacher_id);
}
$stmt->execute();
$subjects_res = $stmt->get_result();
$subjects = [];
while ($row = $subjects_res->fetch_assoc()) {
    $subjects[] = $row;
}
$stmt->close();

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="content-container">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">Subject Management</h1>
            <p class="page-subtitle">Add, organize, and administer courses and academic subjects.</p>
        </div>
        <div>
            <button type="button" class="btn btn-primary" onclick="toggleAddSubjectPanel()">
                + Add New Subject
            </button>
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

    <!-- Collapsible Add/Edit Subject Card -->
    <div class="card" id="addSubjectPanel" style="<?php echo (!empty($error_msg) || isset($_GET['add'])) ? 'display: block;' : 'display: none;'; ?>">
        <div class="card-header-clean">
            <h2 class="card-title" id="formPanelTitle">➕ Add Academic Subject</h2>
            <button type="button" class="btn btn-sm btn-secondary" onclick="toggleAddSubjectPanel()">Cancel</button>
        </div>

        <form method="POST" action="manage_subjects.php" id="subjectForm">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="form_action" id="form_action" value="add">
            <input type="hidden" name="subject_id" id="form_subject_id" value="">

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem;">
                <div class="form-group">
                    <label for="subject_name">Subject Name *</label>
                    <input type="text" id="subject_name" name="subject_name" placeholder="e.g. Data Structures & Algorithms" value="<?php echo e($_POST['subject_name'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="subject_code">Subject Code *</label>
                    <input type="text" id="subject_code" name="subject_code" placeholder="e.g. CS-301" value="<?php echo e($_POST['subject_code'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="class_or_semester">Class / Semester</label>
                    <input type="text" id="class_or_semester" name="class_or_semester" placeholder="e.g. 5th Semester / CS-B" value="<?php echo e($_POST['class_or_semester'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="academic_year">Academic Year</label>
                    <input type="text" id="academic_year" name="academic_year" placeholder="e.g. 2026-2027" value="<?php echo e($_POST['academic_year'] ?? date('Y') . '-' . (date('Y') + 1)); ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="description">Course Description (Optional)</label>
                <textarea id="description" name="description" rows="2" placeholder="Brief outline of syllabus or course objectives..."><?php echo e($_POST['description'] ?? ''); ?></textarea>
            </div>

            <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1rem;">
                <button type="button" class="btn btn-secondary" onclick="toggleAddSubjectPanel()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="submitSubjectBtn">Create Subject</button>
            </div>
        </form>
    </div>

    <!-- Subjects List Table Card -->
    <div class="card">
        <div class="card-header-clean">
            <h2 class="card-title">Authorized Course Offerings (<?php echo count($subjects); ?>)</h2>
        </div>

        <?php if (empty($subjects)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📚</div>
                <h3>No Subjects Added Yet</h3>
                <p style="margin: 0.5rem 0 1.25rem;">You have not defined any course offerings yet. Add a subject to begin taking roll call.</p>
                <button type="button" class="btn btn-primary" onclick="toggleAddSubjectPanel()">
                    Create First Subject
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Subject Name</th>
                            <th>Code</th>
                            <th>Class / Semester</th>
                            <th>Academic Year</th>
                            <th>Status</th>
                            <th>Attendance Logs</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $idx = 1; foreach ($subjects as $sub): ?>
                            <tr>
                                <td style="color: var(--text-muted); font-size: 0.85rem;"><?php echo $idx++; ?></td>
                                <td>
                                    <strong style="color: var(--navy-dark); font-size: 0.95rem;">
                                        📘 <?php echo e($sub['subject_name']); ?>
                                    </strong>
                                    <?php if (!empty($sub['description'])): ?>
                                        <div style="font-size: 0.78rem; color: var(--text-muted); max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                            <?php echo e($sub['description']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="student-class-badge" style="background: var(--primary-light); color: var(--primary);">
                                        <?php echo e($sub['subject_code']); ?>
                                    </span>
                                </td>
                                <td><?php echo !empty($sub['class_or_semester']) ? e($sub['class_or_semester']) : '—'; ?></td>
                                <td><?php echo !empty($sub['academic_year']) ? e($sub['academic_year']) : '—'; ?></td>
                                <td>
                                    <?php if ($sub['status'] === 'active'): ?>
                                        <span class="badge badge-active">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-archived">Archived</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="font-weight: 600; color: var(--text-secondary);">
                                        <?php echo (int)$sub['attendance_count']; ?> logs
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.4rem; align-items: center;">
                                        <?php if ($sub['status'] === 'active'): ?>
                                            <a href="mark_attendance.php?subject=<?php echo urlencode($sub['subject_name']); ?>" class="btn btn-sm btn-primary" title="Record Attendance for this course">
                                                Roll Call
                                            </a>
                                        <?php endif; ?>

                                        <button type="button" class="btn btn-sm btn-secondary" onclick='editSubject(<?php echo json_encode($sub); ?>)' title="Edit Subject Details">
                                            ✏️
                                        </button>

                                        <?php if ($sub['status'] === 'active'): ?>
                                            <a href="manage_subjects.php?action=delete&id=<?php echo $sub['id']; ?>" class="btn btn-sm btn-secondary" style="color: var(--danger);" onclick="return confirm('Archive or remove this subject?');" title="Safe Remove / Archive">
                                                🗑️
                                            </a>
                                        <?php else: ?>
                                            <a href="manage_subjects.php?action=unarchive&id=<?php echo $sub['id']; ?>" class="btn btn-sm btn-secondary" title="Restore Subject">
                                                ↺ Restore
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function toggleAddSubjectPanel() {
        const panel = document.getElementById('addSubjectPanel');
        const isHidden = panel.style.display === 'none' || panel.style.display === '';
        panel.style.display = isHidden ? 'block' : 'none';
        
        if (isHidden) {
            // Reset to Add mode
            document.getElementById('formPanelTitle').innerText = '➕ Add Academic Subject';
            document.getElementById('form_action').value = 'add';
            document.getElementById('form_subject_id').value = '';
            document.getElementById('subject_name').value = '';
            document.getElementById('subject_code').value = '';
            document.getElementById('class_or_semester').value = '';
            document.getElementById('description').value = '';
            document.getElementById('submitSubjectBtn').innerText = 'Create Subject';
            document.getElementById('subject_name').focus();
        }
    }

    function editSubject(sub) {
        const panel = document.getElementById('addSubjectPanel');
        panel.style.display = 'block';

        document.getElementById('formPanelTitle').innerText = '✏️ Edit Subject: ' + sub.subject_name;
        document.getElementById('form_action').value = 'edit';
        document.getElementById('form_subject_id').value = sub.id;
        document.getElementById('subject_name').value = sub.subject_name;
        document.getElementById('subject_code').value = sub.subject_code;
        document.getElementById('class_or_semester').value = sub.class_or_semester || '';
        document.getElementById('academic_year').value = sub.academic_year || '';
        document.getElementById('description').value = sub.description || '';
        document.getElementById('submitSubjectBtn').innerText = 'Save Changes';

        window.scrollTo({ top: panel.offsetTop - 20, behavior: 'smooth' });
    }
</script>

<?php require_once 'includes/footer.php'; ?>
