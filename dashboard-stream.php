<?php
require_once __DIR__ . '/includes/config.php';
date_default_timezone_set('Asia/Manila');

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no'); // Important for Nginx

function getDashboardData(): array {
    $db = getDB();

    $counts = [
        'courses'    => (int)$db->query("SELECT COUNT(*) FROM courses")->fetchColumn(),
        'sections'   => (int)$db->query("SELECT COUNT(*) FROM sections")->fetchColumn(),
        'subjects'   => (int)$db->query("SELECT COUNT(*) FROM subjects")->fetchColumn(),
        'professors' => (int)$db->query("SELECT COUNT(*) FROM professors")->fetchColumn(),
        'rooms'      => (int)$db->query("SELECT COUNT(*) FROM rooms")->fetchColumn(),
        'schedules'  => (int)$db->query("SELECT COUNT(*) FROM schedules")->fetchColumn(),
    ];

    $fullTime    = (int)$db->query("SELECT COUNT(*) FROM professors WHERE employment_type='Full Time'")->fetchColumn();
    $partTime    = (int)$db->query("SELECT COUNT(*) FROM professors WHERE employment_type='Part Time'")->fetchColumn();
    $scheduled   = (int)$db->query("SELECT COUNT(DISTINCT professor_id) FROM schedules")->fetchColumn();

    $byDay = [];
    foreach ($db->query("SELECT day_code, COUNT(*) as cnt FROM schedules GROUP BY day_code")->fetchAll() as $d)
        $byDay[$d['day_code']] = (int)$d['cnt'];

    $sem1 = (int)$db->query("SELECT COUNT(*) FROM schedules WHERE semester='1st Semester'")->fetchColumn();
    $sem2 = (int)$db->query("SELECT COUNT(*) FROM schedules WHERE semester='2nd Semester'")->fetchColumn();

    $recentSchedules = $db->query("
        SELECT sc.*, sec.name AS section_name, sub.code AS subject_code, sub.name AS subject_name,
               r.name AS room_name, r.room_type, p.name AS prof_name
        FROM schedules sc
        JOIN sections  sec ON sec.id = sc.section_id
        JOIN subjects  sub ON sub.id = sc.subject_id
        JOIN rooms     r   ON r.id   = sc.room_id
        JOIN professors p  ON p.id   = sc.professor_id
        ORDER BY sc.id DESC LIMIT 8
    ")->fetchAll(PDO::FETCH_ASSOC);

    $busyRooms = $db->query("
        SELECT r.name, r.room_type, COUNT(*) as cnt
        FROM schedules sc JOIN rooms r ON r.id=sc.room_id
        GROUP BY sc.room_id ORDER BY cnt DESC LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    $topProfs = $db->query("
        SELECT p.name, p.employment_type, COUNT(*) as cnt
        FROM schedules sc JOIN professors p ON p.id=sc.professor_id
        GROUP BY sc.professor_id ORDER BY cnt DESC LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    return compact('counts','fullTime','partTime','scheduled','byDay','sem1','sem2','recentSchedules','busyRooms','topProfs');
}

// Keep alive — stream every 5 seconds
set_time_limit(0);
$lastHash = '';

while (true) {
    $data    = getDashboardData();
    $hash    = md5(json_encode($data));

    if ($hash !== $lastHash) {
        $lastHash = $hash;
        echo "data: " . json_encode($data) . "\n\n";
        ob_flush(); flush();
    }

    // Heartbeat so connection stays alive
    echo ": heartbeat\n\n";
    ob_flush(); flush();

    if (connection_aborted()) break;
    sleep(5);
}