<?php
/**
 * schedules_ajax.php
 * AJAX helper for schedules.php
 * Returns JSON data for dynamic dropdowns.
 */
require_once __DIR__ . '/includes/config.php';
header('Content-Type: application/json');

$db     = getDB();
$action = $_GET['action'] ?? '';

if ($action === 'subjects') {
    $sectionId = (int)($_GET['section_id'] ?? 0);
    $semester  = $_GET['semester'] ?? '';
    $courseId  = (int)($_GET['course_id'] ?? 0);

    if (!$sectionId || !$semester || !$courseId) {
        echo json_encode(['subjects' => []]);
        exit;
    }

    // Get year_level from section
    $stmt = $db->prepare("SELECT year_level FROM sections WHERE id = ? AND course_id = ?");
    $stmt->execute([$sectionId, $courseId]);
    $yearLevel = $stmt->fetchColumn();

    if (!$yearLevel) {
        echo json_encode(['subjects' => []]);
        exit;
    }

    $stmt = $db->prepare("
        SELECT id, code, name
        FROM subjects
        WHERE course_id = ?
          AND year_level = ?
          AND semester = ?
        ORDER BY code
    ");
    $stmt->execute([$courseId, $yearLevel, $semester]);
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['subjects' => $subjects]);
    exit;
}

// Unknown action
echo json_encode(['error' => 'Unknown action']);