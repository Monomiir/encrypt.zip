<?php
$cli = (php_sapi_name() === 'cli');

if (!$cli) {
    require_once __DIR__ . '/includes/botblock.inc.php';
    block_if_bot();
}

$mode = $argv[1] ?? $_GET['mode'] ?? null;
$now = time();

// Purge expired messages.
if ($mode === 'msg') {
    $rootDir = __DIR__ . '/messages';
    $expired = 24 * 60 * 60;
    $deleted = 0;

    foreach (glob("$rootDir/*/*/*.json") as $file) {
        if (filemtime($file) < ($now - $expired)) {
            if (unlink($file)) $deleted++;
        }
    }

    // Purge blank directory.
    foreach (glob("$rootDir/*/*", GLOB_ONLYDIR) as $subdir) {
        if (count(glob("$subdir/*")) === 0) rmdir($subdir);
    }
    foreach (glob("$rootDir/*", GLOB_ONLYDIR) as $prefixDir) {
        if (count(glob("$prefixDir/*")) === 0) rmdir($prefixDir);
    }

    if ($cli) {
        echo "[gc.php] $deleted expired message(s) deleted.\n";
        exit;
    }
}

// Purge IP based limitation
if ($mode === 'rate') {
    $rateDir = __DIR__ . '/rate_limit';
    $deleted = 0;

    foreach (glob("$rateDir/*.json") as $file) {
        $data = json_decode(file_get_contents($file), true);
        $lastTime = $data['first'] ?? $now;
        $blockedUntil = $data['blocked_until'] ?? 0;

        if (!$data || ($now - $lastTime > 3600 && $now > $blockedUntil)) {
            if (unlink($file)) $deleted++;
        }
    }

    if ($cli) {
        echo "[gc.php] $deleted expired rate-limit record(s) deleted.\n";
        exit;
    }
}

// Blocking web access
http_response_code(403);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Forbidden - encrypt.zip</title>
  <meta property="og:title" content="Encrypt.zip - Forbidden page">
  <meta property="og:description" content="This page is not accessible via web browser.">
  <meta property="og:type" content="website">
  <meta property="og:url" content="https://encrypt.zip/">
  <meta property="og:image" content="https://encrypt.zip/assets/og-image.png">
  <link rel="icon" href="/assets/favicon.png" type="image/png">
  <style>
    body {
      background: #000;
      color: #ccc;
      font-family: 'Courier New', monospace;
      padding: 40px;
      text-align: center;
    }
    h1 {
      font-size: 24px;
      color: #f00;
    }
    .message {
      margin-top: 20px;
      font-size: 16px;
      color: #aaa;
    }
    footer {
      margin-top: 40px;
      font-size: 14px;
      color: #555;
    }
  </style>
</head>
<body>
  <h1>🚫 403 - Forbidden</h1>
  <div class="message">
    This page is not accessible via web browser.<br>
  </div>
  <footer>Powered by monomiir</footer>
</body>
</html>
