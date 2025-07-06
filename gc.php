<?php
$cli = (php_sapi_name() === 'cli');

// ✅ 삭제 대상 디렉터리
$dir = __DIR__ . '/messages';
$now = time();
$expired = 24 * 60 * 60; // 24시간 기준
$deleted = 0;

foreach (glob($dir . '/*.json') as $file) {
    if (filemtime($file) < ($now - $expired)) {
        if (unlink($file)) $deleted++;
    }
}

if ($cli) {
    echo "[gc.php] $deleted expired message(s) deleted.\n";
    exit;
}

// ✅ 브라우저 접근 시 UI로 출력
http_response_code(403);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Forbidden - encrypt.zip</title>
  <!-- Open Graph -->
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
