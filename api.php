<?php
require_once __DIR__ . '/includes/config.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$db = getDB();

switch ($action) {
    case 'get_sections':
        $courseId = (int)($_GET['course_id'] ?? 0);
        if ($courseId) {
            $stmt = $db->prepare("SELECT id, name, year_level FROM sections WHERE course_id = ? ORDER BY year_level, name");
            $stmt->execute([$courseId]);
        } else {
            $stmt = $db->query("SELECT id, name, year_level FROM sections ORDER BY year_level, name");
        }
        echo json_encode($stmt->fetchAll());
        break;

    case 'get_subjects':
        $courseId  = (int)($_GET['course_id'] ?? 0);
        $yearLevel = $_GET['year_level'] ?? '';
        $semester  = $_GET['semester'] ?? '';
        $where = ['1=1'];
        $params = [];
        if ($courseId)  { $where[] = 'course_id = ?';  $params[] = $courseId; }
        if ($yearLevel) { $where[] = 'year_level = ?'; $params[] = $yearLevel; }
        if ($semester)  { $where[] = 'semester = ?';   $params[] = $semester; }
        $sql = "SELECT id, code, name, year_level, semester FROM subjects WHERE " . implode(' AND ', $where) . " ORDER BY code";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll());
        break;

    case 'get_section_year':
        $secId = (int)($_GET['section_id'] ?? 0);
        $stmt = $db->prepare("SELECT year_level FROM sections WHERE id = ?");
        $stmt->execute([$secId]);
        $row = $stmt->fetch();
        echo json_encode(['year_level' => $row['year_level'] ?? '']);
        break;

    default:
        echo json_encode(['error' => 'Unknown action']);
}
