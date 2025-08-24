<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

try {
  $raw = file_get_contents('php://input');
  $data = $raw ? json_decode($raw, true) : $_POST;

  $name = trim((string)($data['name'] ?? ''));
  $email = trim((string)($data['email'] ?? ''));
  $message = trim((string)($data['message'] ?? ''));

  if ($name === '') {
    echo json_encode(['status' => 'error', 'message' => 'Name is required']);
    exit;
  }
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid email']);
    exit;
  }

  $record = [
    'name' => $name,
    'email' => strtolower($email),
    'message' => $message,
    'submitted_at' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DateTimeInterface::ATOM),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
    'ua' => $_SERVER['HTTP_USER_AGENT'] ?? null,
  ];

  $file = __DIR__ . '/submissions.json';

  $entries = [];
  if (file_exists($file) && filesize($file) > 0) {
    $json = file_get_contents($file);
    $entries = json_decode($json, true);
    if (!is_array($entries)) $entries = [];
  }

  $entries[] = $record;

  $fh = fopen($file, 'c+');
  if (!$fh) {
    echo json_encode(['status' => 'error', 'message' => 'Storage error']);
    exit;
  }
  if (!flock($fh, LOCK_EX)) {
    fclose($fh);
    echo json_encode(['status' => 'error', 'message' => 'Lock error']);
    exit;
  }

  ftruncate($fh, 0);
  rewind($fh);
  fwrite($fh, json_encode($entries, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
  fflush($fh);
  flock($fh, LOCK_UN);
  fclose($fh);

  echo json_encode(['status' => 'success', 'message' => 'Saved']);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['status' => 'error', 'message' => 'Server error']);
}
