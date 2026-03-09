<?php
require "../config/auth.php";
require "../config/database.php";
require "../config/flash.php";

requireLogin();

$actorId = (int)($_SESSION['user']['id'] ?? 0);
$actorRole = (string)($_SESSION['user']['role'] ?? '');
$isAdmin = ($actorRole === 'admin');

$allowedRoles = [
  'admin',
  'manager',
  'procurement_staff',
  'project_staff',
  'asset',
  'mro_staff',
  'warehouse_staff',
];

$action = (string)($_POST['action'] ?? '');
$targetUserId = isset($_POST['user_id']) && ctype_digit((string)$_POST['user_id']) ? (int)$_POST['user_id'] : 0;

if ($targetUserId <= 0) {
  set_flash('error', 'Invalid user target.');
  header('Location: ../views/user_edit.php');
  exit;
}

if (!$isAdmin && $targetUserId !== $actorId) {
  http_response_code(403);
  set_flash('error', 'Not allowed.');
  header('Location: ../views/user_edit.php');
  exit;
}

$redirect = '../views/user_edit.php';
if ($isAdmin && $targetUserId > 0) {
  $redirect .= '?id=' . $targetUserId;
}

function redirect_with_section(string $redirect, string $section): void
{
  $sep = str_contains($redirect, '?') ? '&' : '?';
  header('Location: ' . $redirect . $sep . 'section=' . $section);
  exit;
}

function ensure_avatar_column(PDO $pdo): void
{
  $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'avatar_path'");
  $exists = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
  if (!$exists) {
    $pdo->exec("ALTER TABLE users ADD COLUMN avatar_path VARCHAR(255) NULL AFTER role");
  }
}

if ($action === 'update_profile') {
  $fullname = trim((string)($_POST['fullname'] ?? ''));
  $email = trim((string)($_POST['email'] ?? ''));

  if ($fullname === '' || $email === '') {
    set_flash('error', 'Full name and email are required.');
    redirect_with_section($redirect, 'profile');
  }

  $role = null;
  if ($isAdmin) {
    $role = (string)($_POST['role'] ?? '');
    if (!in_array($role, $allowedRoles, true)) {
      set_flash('error', 'Invalid role selected.');
      redirect_with_section($redirect, 'profile');
    }
  }

  try {
    $sql = $isAdmin
      ? "UPDATE users SET fullname = ?, email = ?, role = ? WHERE id = ?"
      : "UPDATE users SET fullname = ?, email = ? WHERE id = ?";
    $params = $isAdmin
      ? [$fullname, $email, $role, $targetUserId]
      : [$fullname, $email, $targetUserId];

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    if ($targetUserId === $actorId) {
      $_SESSION['user']['fullname'] = $fullname;
      $_SESSION['user']['email'] = $email;
      if ($isAdmin && $role !== null) {
        $_SESSION['user']['role'] = $role;
      }
    }

    $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
        ->execute([$actorId, "Updated profile for user ID $targetUserId"]);

    set_flash('success', 'Profile updated successfully.');
  } catch (PDOException $e) {
    set_flash('error', 'Unable to update profile. Email may already exist.');
  }

  redirect_with_section($redirect, 'profile');
}

if ($action === 'update_photo') {
  try {
    ensure_avatar_column($pdo);

    if (!isset($_FILES['photo'])) {
      set_flash('error', 'No photo file received.');
      redirect_with_section($redirect, 'profile');
    }

    $file = $_FILES['photo'];
    if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
      set_flash('error', 'Photo upload failed.');
      redirect_with_section($redirect, 'profile');
    }

    $maxBytes = 2 * 1024 * 1024;
    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > $maxBytes) {
      set_flash('error', 'Photo must be up to 2MB.');
      redirect_with_section($redirect, 'profile');
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    $mime = '';
    if (function_exists('finfo_open')) {
      $f = finfo_open(FILEINFO_MIME_TYPE);
      if ($f) {
        $mime = (string)finfo_file($f, $tmp);
        finfo_close($f);
      }
    }

    $allowed = [
      'image/jpeg' => 'jpg',
      'image/png' => 'png',
      'image/webp' => 'webp',
    ];
    if (!isset($allowed[$mime])) {
      set_flash('error', 'Only JPG, PNG, or WEBP is allowed.');
      redirect_with_section($redirect, 'profile');
    }

    $ext = $allowed[$mime];
    $uploadDir = dirname(__DIR__) . '/public/uploads/profile';
    if (!is_dir($uploadDir)) {
      mkdir($uploadDir, 0755, true);
    }

    $newName = 'u' . $targetUserId . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destFs = $uploadDir . '/' . $newName;

    if (!move_uploaded_file($tmp, $destFs)) {
      set_flash('error', 'Could not save uploaded photo.');
      redirect_with_section($redirect, 'profile');
    }

    $oldStmt = $pdo->prepare("SELECT avatar_path FROM users WHERE id = ? LIMIT 1");
    $oldStmt->execute([$targetUserId]);
    $oldPath = (string)($oldStmt->fetchColumn() ?: '');

    $newPath = 'public/uploads/profile/' . $newName;
    $upStmt = $pdo->prepare("UPDATE users SET avatar_path = ? WHERE id = ?");
    $upStmt->execute([$newPath, $targetUserId]);

    if ($targetUserId === $actorId) {
      $_SESSION['user']['avatar_path'] = $newPath;
    }

    if ($oldPath !== '' && str_starts_with($oldPath, 'public/uploads/profile/')) {
      $oldFs = dirname(__DIR__) . '/' . $oldPath;
      if (is_file($oldFs)) {
        @unlink($oldFs);
      }
    }

    $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
      ->execute([$actorId, "Updated profile photo for user ID $targetUserId"]);

    set_flash('success', 'Profile photo updated.');
  } catch (Throwable $e) {
    set_flash('error', 'Unable to update profile photo.');
  }

  redirect_with_section($redirect, 'profile');
}

if ($action === 'update_password') {
  $current = (string)($_POST['current_password'] ?? '');
  $new = (string)($_POST['new_password'] ?? '');
  $confirm = (string)($_POST['confirm_password'] ?? '');

  if ($new === '' || $confirm === '') {
    set_flash('error', 'New password and confirmation are required.');
    redirect_with_section($redirect, 'password');
  }

  if (strlen($new) < 6) {
    set_flash('error', 'Password must be at least 6 characters.');
    redirect_with_section($redirect, 'password');
  }

  if ($new !== $confirm) {
    set_flash('error', 'Password confirmation does not match.');
    redirect_with_section($redirect, 'password');
  }

  $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
  $stmt->execute([$targetUserId]);
  $target = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$target) {
    set_flash('error', 'User not found.');
    redirect_with_section($redirect, 'password');
  }

  $isSelf = ($targetUserId === $actorId);
  if ($isSelf || !$isAdmin) {
    if ($current === '' || !password_verify($current, (string)$target['password'])) {
      set_flash('error', 'Current password is incorrect.');
      redirect_with_section($redirect, 'password');
    }
  }

  $hash = password_hash($new, PASSWORD_DEFAULT);
  $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
  $update->execute([$hash, $targetUserId]);

  $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?,?)")
      ->execute([$actorId, "Updated password for user ID $targetUserId"]);

  set_flash('success', 'Password updated successfully.');
  redirect_with_section($redirect, 'password');
}

set_flash('error', 'Invalid request.');
redirect_with_section($redirect, 'profile');
