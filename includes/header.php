<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';

$flash = getFlash();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

$navItems = [
    ['page' => 'index',      'label' => 'Dashboard',        'icon' => 'grid'],
    ['page' => 'courses',    'label' => 'Courses',           'icon' => 'book'],
    ['page' => 'sections',   'label' => 'Sections',          'icon' => 'users'],
    ['page' => 'subjects',   'label' => 'Subjects',          'icon' => 'clipboard'],
    ['page' => 'professors', 'label' => 'Professors',        'icon' => 'user'],
    ['page' => 'rooms',      'label' => 'Rooms',             'icon' => 'building'],
    ['page' => 'schedules',  'label' => 'Schedules',         'icon' => 'calendar'],
    ['page' => 'timetable',  'label' => 'Timetable View',    'icon' => 'table'],
    ['page' => 'conflicts',  'label' => 'Conflict Checker',  'icon' => 'alert'],
];

$icons = [
    'grid'      => '<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>',
    'book'      => '<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>',
    'users'     => '<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
    'clipboard' => '<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>',
    'user'      => '<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>',
    'building'  => '<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>',
    'calendar'  => '<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>',
    'table'     => '<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>',
    'alert'     => '<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>',
];

// Dynamic academic year:
// Academic year starts in June (month 6) — adjust if your school uses a different start month.
$month   = (int)date('n');
$year    = (int)date('Y');
$ayStart = $month >= 6 ? $year : $year - 1;
$ayEnd   = $ayStart + 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= APP_NAME ?> – <?= h($pageTitle ?? 'Dashboard') ?></title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<!-- Sidebar -->
<nav class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <h1><?= APP_NAME ?></h1>
    <p>COLLEGE SCHEDULING SYSTEM</p>
  </div>
  <div class="nav-section">
    <div class="nav-label">Overview</div>
    <?php foreach (array_slice($navItems, 0, 1) as $item): ?>
    <a href="<?= $item['page'] ?>.php" class="nav-item <?= $currentPage === $item['page'] ? 'active' : '' ?>">
      <?= $icons[$item['icon']] ?> <?= h($item['label']) ?>
    </a>
    <?php endforeach; ?>
  </div>
  <div class="nav-section">
    <div class="nav-label">Management</div>
    <?php foreach (array_slice($navItems, 1, 5) as $item): ?>
    <a href="<?= $item['page'] ?>.php" class="nav-item <?= $currentPage === $item['page'] ? 'active' : '' ?>">
      <?= $icons[$item['icon']] ?> <?= h($item['label']) ?>
    </a>
    <?php endforeach; ?>
  </div>
  <div class="nav-section">
    <div class="nav-label">Scheduling</div>
    <?php foreach (array_slice($navItems, 6) as $item): ?>
    <a href="<?= $item['page'] ?>.php" class="nav-item <?= $currentPage === $item['page'] ? 'active' : '' ?>">
      <?= $icons[$item['icon']] ?> <?= h($item['label']) ?>
    </a>
    <?php endforeach; ?>
  </div>
</nav>

<!-- Main -->
<div class="main-content">
  <div class="topbar">
    <div class="topbar-left">
      <button class="mobile-menu-btn" onclick="toggleSidebar()">☰</button>
      <span class="topbar-title"><?= h($pageTitle ?? 'Dashboard') ?></span>
    </div>
    <div class="topbar-right">
      <span class="badge">A.Y. <?= $ayStart ?>–<?= $ayEnd ?></span>
    </div>
  </div>

  <div class="content-area">
    <?php if ($flash): ?>
    <div class="alert alert-<?= h($flash['type']) ?> mb-4" id="flash-msg">
      <?= h($flash['msg']) ?>
      <button onclick="this.parentElement.remove()" class="close-btn">×</button>
    </div>
    <?php endif; ?>