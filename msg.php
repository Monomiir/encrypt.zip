<?php
session_start();

require_once __DIR__ . '/includes/botblock.inc.php';
block_if_bot();

$step = null;

function isIpRateLimited($maxRequests = 10, $windowSeconds = 60, $blockDuration = 600) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $dir = __DIR__ . '/rate_limit';
    if (!is_dir($dir)) mkdir($dir, 0700, true);

    $file = "$dir/" . md5($ip) . '.json';
    $now = time();
    $data = ['count' => 0, 'first' => $now, 'blocked_until' => 0];

    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true) ?? $data;
    }

    if ($data['blocked_until'] > $now) return true;

    if ($now - $data['first'] > $windowSeconds) {
        $data['count'] = 1;
        $data['first'] = $now;
    } else {
        $data['count']++;
    }

    if ($data['count'] > $maxRequests) {
        $data['blocked_until'] = $now + $blockDuration;
        file_put_contents($file, json_encode($data));
        return true;
    }

    file_put_contents($file, json_encode($data));
    return false;
}

if (isIpRateLimited()) {
    http_response_code(429);
    $step = 'rate_limit';
}

$id = $_GET['id'] ?? $_POST['id'] ?? null;
$csrf_token = $_SESSION['csrf'] ?? bin2hex(random_bytes(16));
$_SESSION['csrf'] = $csrf_token;

$cipher = '';
$algorithm = '';

if ($step !== 'rate_limit') {
    if ($id && preg_match('/^[a-f0-9]{32}$/', $id)) {
        $subdir = substr($id, 0, 2) . '/' . substr($id, 2, 2);
        $filepath = __DIR__ . "/messages/{$subdir}/{$id}.json";
    } else {
        $filepath = null;
    }

    if (!$id || !$filepath || !file_exists($filepath)) {
        $step = '404';
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf'] ?? '')) {
            die('Invalid CSRF token.');
        }
        $data = json_decode(file_get_contents($filepath), true);
        unlink($filepath);
        $cipher = $data['cipher'] ?? '';
        $algorithm = strtolower($data['alg'] ?? '');
        $step = 'show';
    } else {
        $step = 'input';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>One-time message - encrypt.zip</title>
  <link rel="icon" href="/assets/favicon.png" type="image/png">
  <meta property="og:title" content="🔒 Encrypted Message">
  <meta property="og:description" content="This is a one-time encrypted message. Click to view.">
  <meta property="og:image" content="https://encrypt.zip/assets/og-image.png">
  <meta property="og:url" content="https://encrypt.zip/msg.php">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body {
      background: #000;
      color: #ccc;
      font-family: 'Courier New', monospace;
      padding: 20px;
    }
    h1 { font-size: 32px; text-align: center; color: #0f0; }
    .label { font-weight: bold; margin-top: 20px; font-size: 18px; }
    .copyable {
      background: #111;
      border: 1px dashed #888;
      padding: 10px;
      margin-top: 10px;
      cursor: pointer;
      white-space: pre-wrap;
      word-break: break-word;
      overflow-wrap: break-word;
      overflow-x: auto;
      box-sizing: border-box;
    }
    .copied-msg {
      color: #0f0;
      margin-left: 5px;
    }
    .warning {
      color: orange;
      font-size: 14px;
      margin-top: 10px;
    }
    footer {
      text-align: center;
      margin-top: 50px;
      font-size: 14px;
      color: #555;
    }
    button {
      background: #000;
      color: #ccc;
      border: 1px solid #ccc;
      padding: 6px 12px;
      margin-top: 10px;
      cursor: pointer;
    }
  </style>
</head>
<body>
  <h1>🔒 encrypt.zip</h1>

<?php if ($step === 'rate_limit'): ?>
  <div class="label">429 - Too Many Requests</div>
  <div class="warning">⚠️ You have exceeded the request limit. Please wait a moment and try again.</div>

<?php elseif ($step === '404'): ?>
  <div class="label">404 - Message Not Found</div>
  <div class="warning">This one-time message has already been accessed or is invalid.</div>

<?php elseif ($step === 'input'): ?>
  <form method="post">
    <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
    <div class="label">View Encrypted Message</div>
    <button type="submit">View Message</button>
    <div class="warning">This is a one-time link. Clicking this button will reveal the message and delete it.</div>
  </form>

<?php elseif ($step === 'show'): ?>
  <div class="label"><?= $algorithm === 'plain-link' ? 'Plain Message:' : 'Encrypted Message:' ?></div>
  <div class="copyable" data-clip="<?= htmlspecialchars($cipher) ?>"><?= htmlspecialchars($cipher) ?></div>

  <?php if ($algorithm !== 'plain-link'): ?>
    <div class="label">Algorithm:</div>
    <div class="copyable" data-clip="<?= htmlspecialchars($algorithm) ?>"><?= htmlspecialchars(strtoupper($algorithm)) ?></div>
    <div class="warning">This message will not be shown again. Save the content securely.</div>
  <?php else: ?>
    <div class="warning">This is a plain message. No encryption key was used.</div>
  <?php endif; ?>
<?php endif; ?>

  <footer>Powered by monomiir</footer>

  <script>
    document.querySelectorAll('.copyable').forEach(el => {
      const raw = el.dataset.clip;
      el.addEventListener('click', () => {
        navigator.clipboard.writeText(raw).then(() => {
          const old = el.querySelector('.copied-msg');
          if (!old) {
            const msg = document.createElement('span');
            msg.className = 'copied-msg';
            msg.textContent = ' Copied!';
            el.appendChild(msg);
          }
        });
      });
    });
  </script>
</body>
</html>
