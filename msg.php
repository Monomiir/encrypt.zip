<?php
$id = $_GET['id'] ?? null;
$filepath = __DIR__ . "/messages/{$id}.json";
$notFound = false;

if ($id && file_exists($filepath)) {
    $data = json_decode(file_get_contents($filepath), true);
    unlink($filepath); // 1회 조회 후 삭제
    $cipher = $data['cipher'] ?? '';
    $key = $data['key'] ?? '';
    $algorithm = $data['alg'] ?? '';
} else {
    $notFound = true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>encrypt.zip</title>
  <link rel="icon" href="/assets/favicon.png" type="image/png">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>One-time message - encrypt.zip</title>
  <!-- Open Graph -->
  <meta property="og:title" content="Encrypt.zip - One-time message">
  <meta property="og:description" content="One-time message. this page will not be shown again.">
  <meta property="og:type" content="website">
  <meta property="og:url" content="https://encrypt.zip/">
  <meta property="og:image" content="https://encrypt.zip/assets/og-image.png">
  <style>
    body {
      background: #000;
      color: #ccc;
      font-family: 'Courier New', monospace;
      padding: 20px;
    }
    h1 { font-size: 24px; }
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
  </style>
</head>
<body>
  <h1>🔒 encrypt.zip</h1>

<?php if ($notFound): ?>
  <div class="label">404 - Message Not Found</div>
  <div class="warning">This one-time message has already been accessed or is invalid.</div>
<?php else: ?>
  <div class="label">Encrypted Message:</div>
  <div class="copyable" data-clip="<?= htmlspecialchars($cipher) ?>"><?= htmlspecialchars($cipher) ?></div>

  <?php if (!empty($key)): ?>
    <div class="label">Key:</div>
    <div class="copyable" data-clip="<?= $key ?>"><?= $key ?></div>

    <div class="label">Algorithm:</div>
    <div class="copyable" data-clip="<?= $algorithm ?>"><?= $algorithm ?></div>

    <div class="warning">
      This message will not be shown again. Save the content securely if you want.
    </div>
  <?php else: ?>
    <div class="warning">This message is not encrypted. (plain text) However, the message will not be shown again.</div>
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
