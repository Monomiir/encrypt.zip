<?php
$cli = (php_sapi_name() === 'cli');

require_once __DIR__ . '/includes/botblock.inc.php';
block_if_bot();

$rootDir = __DIR__ . '/messages';
$now = time();
$expired = 24 * 60 * 60;
$deleted = 0;

// 🔍 하위 구조: messages/aa/bb/*.json
foreach (glob("$rootDir/*/*/*.json") as $file) {
    if (filemtime($file) < ($now - $expired)) {
        if (unlink($file)) {
            $deleted++;
        }
    }
}

// 🧹 빈 폴더 정리 (aa/bb 구조)
foreach (glob("$rootDir/*/*", GLOB_ONLYDIR) as $subdir) {
    if (count(glob("$subdir/*")) === 0) {
        rmdir($subdir);
    }
}
foreach (glob("$rootDir/*", GLOB_ONLYDIR) as $prefixDir) {
    if (count(glob("$prefixDir/*")) === 0) {
        rmdir($prefixDir);
    }
}

if ($cli) {
    echo "[gc.php] $deleted expired message(s) deleted.\n";
    exit;
}

// 🌐 웹 접근 차단
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
