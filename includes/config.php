<?php
// ============================================================
// DATABASE CONFIGURATION
// ============================================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');          // Change to your MySQL username
define('DB_PASS', '');              // Change to your MySQL password
define('DB_NAME', 'college_scheduling');
define('APP_NAME', 'EduSchedule Pro');
define('APP_VERSION', '1.0.0');

// ============================================================
// DATABASE CONNECTION (PDO)
// ============================================================
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

// ============================================================
// HELPER FUNCTIONS
// ============================================================
function h(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function redirect(string $url): void {
    header("Location: $url");
    exit;
}

function flashMessage(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

function timeToMinutes(string $time): int {
    [$h, $m] = explode(':', $time);
    return (int)$h * 60 + (int)$m;
}

function formatTime(string $time): string {
    $minutes = timeToMinutes($time);
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    $ampm = $h >= 12 ? 'PM' : 'AM';
    $h12 = $h > 12 ? $h - 12 : ($h === 0 ? 12 : $h);
    return sprintf('%d:%02d %s', $h12, $m, $ampm);
}

function formatTimeRange(string $start, string $end): string {
    return formatTime($start) . ' – ' . formatTime($end);
}

function dayFull(string $d): string {
    $days = ['M' => 'Monday', 'T' => 'Tuesday', 'W' => 'Wednesday',
             'Th' => 'Thursday', 'F' => 'Friday', 'Sa' => 'Saturday'];
    return $days[$d] ?? $d;
}

function sanitize(string $str): string {
    return trim(htmlspecialchars(strip_tags($str)));
}
