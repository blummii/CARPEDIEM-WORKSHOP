<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Optional role-based enforcement (only for accounts page for now)
require_once __DIR__ . '/../../config/db.php';

$roleCol = null;
$roleValue = null;
$cols = [];
$colsRes = $conn->query('SHOW COLUMNS FROM Admins');
if ($colsRes) {
  while ($c = $colsRes->fetch_assoc()) $cols[] = $c['Field'];
}

$candidates = ['Role', 'Quyen', 'quyen', 'Phan_quyen', 'phan_quyen', 'Loai_admin', 'Permission', 'Permissions', 'Quyen_admin'];
foreach ($candidates as $cand) {
  if (in_array($cand, $cols, true)) {
    $roleCol = $cand;
    break;
  }
}

if ($roleCol) {
  $rid = (string)($_SESSION['admin_id'] ?? '');
  $st = $conn->prepare("SELECT {$roleCol} FROM Admins WHERE Ma_admin = ? LIMIT 1");
  if ($st) {
    $st->bind_param('s', $rid);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $roleValue = $row ? ($row[$roleCol] ?? null) : null;
  }
}

$isAdmin = true;
if ($roleCol) {
  $s = strtolower(trim((string)$roleValue));
  $isAdmin = in_array($s, ['admin', '1', 'true', 'full', 'super'], true);
}

$page = basename($_SERVER['PHP_SELF'] ?? '');
if ($page === 'accounts.php' && !$isAdmin) {
  header('Location: index.php');
  exit;
}