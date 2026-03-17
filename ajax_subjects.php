<?php
/**
 * ajax_subjects.php
 * Returns subjects for the schedule modal dropdown:
 *   - Regular subjects filtered by course/year/semester (with duplicate exclusion)
 *   - ALL Consultation subjects (global, always shown, never duplicate-filtered)
 */
require_once __DIR__ . '/includes/config.php';
header('Content-Type: application/json');

$db        = getDB();
$courseId  = (int)($_GET['course_id']  ?? 0);
$yearLevel = sanitize($_GET['year_level'] ?? '');
$semester  = sanitize($_GET['semester']   ?? '');
$sectionId = (int)($_GET['section_id']   ?? 0);
$editId    = (int)($_GET['edit_id']      ?? 0);

if (!$courseId || !$yearLevel || !$semester) {
    echo json_encode([]);
    exit;
}

/* ── Excluded REGULAR subject IDs (already scheduled for this section+semester) ── */
$excludedIds = [];
if ($sectionId) {
    $exSql = "SELECT DISTINCT s.subject_id
              FROM schedules s
              JOIN subjects sub ON sub.id = s.subject_id
              WHERE s.section_id = ?
                AND s.semester   = ?
                AND (sub.subject_type IS NULL OR sub.subject_type = 'Regular')";
    $exParams = [$sectionId, $semester];
    if ($editId) {
        $exSql   .= " AND s.id != ?";
        $exParams[] = $editId;
    }
    $exStmt = $db->prepare($exSql);
    $exStmt->execute($exParams);
    $excludedIds = $exStmt->fetchAll(PDO::FETCH_COLUMN);
}

/* ── Regular subjects for this course/year/semester ── */
$regSql    = "SELECT id, code, name, units, hours_week, professor_id, 'Regular' AS subject_type
              FROM subjects
              WHERE course_id  = ?
                AND year_level = ?
                AND semester   = ?
                AND (subject_type IS NULL OR subject_type = 'Regular')";
$regParams = [$courseId, $yearLevel, $semester];

if (!empty($excludedIds)) {
    $ph        = implode(',', array_fill(0, count($excludedIds), '?'));
    $regSql   .= " AND id NOT IN ($ph)";
    $regParams = array_merge($regParams, $excludedIds);
}
$regSql .= " ORDER BY code";

$regStmt = $db->prepare($regSql);
$regStmt->execute($regParams);
$regular = $regStmt->fetchAll();

/* ── ALL Consultation subjects (global — never filtered out) ── */
$cStmt = $db->query("SELECT id, code, name, units, hours_week, professor_id, 'Consultation' AS subject_type
                     FROM subjects
                     WHERE subject_type = 'Consultation'
                     ORDER BY code");
$consultation = $cStmt->fetchAll();

echo json_encode(array_merge($regular, $consultation));