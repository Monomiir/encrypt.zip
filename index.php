<?php
function generateRandomKey($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

function isRateLimited() {
    session_start();
    $now = time();
    if (isset($_SESSION['last_action']) && $now - $_SESSION['last_action'] < 3) return true;
    $_SESSION['last_action'] = $now;
    return false;
}

$allowedCiphers = [
    "aes-128-cbc", "aes-192-cbc", "aes-256-cbc",
    "aes-128-ctr", "aes-192-ctr", "aes-256-ctr",
    "aes-128-ecb", "aes-192-ecb", "aes-256-ecb",
    "plain-link"
];

$output = '';
$generatedKey = '';
$algorithmUsed = '';
$onetimeLink = '';
$isPlainLink = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['encrypt_text'])) {
    if (isRateLimited()) {
        $output = 'Rate limit exceeded.';
    } else {
        $text = $_POST['encrypt_text'];
        $cipher = $_POST['algorithm'];

        if (!in_array($cipher, $allowedCiphers)) {
            $output = 'Unsupported algorithm.';
        } elseif (strlen($text) < 1) {
            $output = 'Text is required.';
        } else {
            $algorithmUsed = strtoupper($cipher);

            if ($cipher === 'plain-link') {
                $payload = $text;
                $key = '';
                $isPlainLink = true;
                $output = '';
            } else {
                $key = generateRandomKey(32);
                $ivlen = openssl_cipher_iv_length($cipher);

                if ($ivlen > 0) {
                    $iv = openssl_random_pseudo_bytes($ivlen);
                    $encrypted = openssl_encrypt($text, $cipher, $key, 0, $iv);
                    if ($encrypted === false) {
                        $output = 'Encryption failed.';
                    } else {
                        $payload = base64_encode($iv . "::" . $encrypted);
                        $output = $payload;
                        $generatedKey = $key;
                    }
                } else {
                    $encrypted = openssl_encrypt($text, $cipher, $key, 0);
                    if ($encrypted === false) {
                        $output = 'Encryption failed.';
                    } else {
                        $payload = base64_encode($encrypted);
                        $output = $payload;
                        $generatedKey = $key;
                    }
                }
            }

            $id = bin2hex(random_bytes(8));
            $data = json_encode([
                'cipher' => $payload,
                'key' => $key,
                'alg' => $algorithmUsed
            ]);
            file_put_contents(__DIR__ . "/messages/{$id}.json", $data);
            $onetimeLink = "https://encrypt.zip/msg.php?id=" . $id;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['decrypt_text'])) {
    if (isRateLimited()) {
        $output = 'Rate limit exceeded.';
    } else {
        $ciphertext = $_POST['decrypt_text'];
        $cipher = $_POST['algorithm'];
        $key = $_POST['decrypt_key'] ?? '';

        if (!in_array($cipher, $allowedCiphers) || $cipher === 'plain-link') {
            $output = 'Unsupported algorithm.';
        } elseif (strlen($key) < 8) {
            $output = 'Decryption key must be at least 8 characters.';
        } else {
            $decoded = base64_decode($ciphertext);

            if (strpos($decoded, '::') !== false) {
                list($iv, $encrypted) = explode("::", $decoded, 2);
                $decrypted = openssl_decrypt($encrypted, $cipher, $key, 0, $iv);
                $output = $decrypted !== false ? $decrypted : 'Decryption failed.';
            } else {
                $decrypted = openssl_decrypt($decoded, $cipher, $key, 0);
                $output = $decrypted !== false ? $decrypted : 'Decryption failed.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Online Text Encrypter - encrypt.zip</title>
  <link rel="icon" href="/assets/favicon.png" type="image/png">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Open Graph -->
  <meta property="og:title" content="Encrypt.zip - Online Text Encrypter">
  <meta property="og:description" content="Encrypt and decrypt text securely using AES base">
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
    textarea, select, input[type="text"] {
      background: #111;
      color: #ccc;
      width: 100%;
      padding: 8px;
      margin-top: 5px;
      border: 1px solid #555;
      box-sizing: border-box;
    }
    button {
      background: #000;
      color: #ccc;
      border: 1px solid #ccc;
      padding: 6px 12px;
      margin-top: 10px;
      margin-right: 10px;
      cursor: pointer;
    }
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

  <form method="post">
    <div class="label">Encryption</div>
    <textarea name="encrypt_text" rows="5" placeholder="Enter text to encrypt..."></textarea>

    <label class="label">Algorithm:</label>
    <select name="algorithm">
      <?php foreach ($allowedCiphers as $alg): ?>
        <option value="<?= $alg ?>"><?= strtoupper($alg) ?></option>
      <?php endforeach; ?>
    </select><br>

    <button type="submit">Encrypt</button>
    <button type="reset">Reset</button>
  </form>

  <form method="post">
    <div class="label">Decryption</div>
    <textarea name="decrypt_text" rows="5" placeholder="Enter encrypted text..."></textarea>

    <label class="label">Decryption Key:</label>
    <input type="text" name="decrypt_key" placeholder="Enter decryption key...">

    <label class="label">Algorithm:</label>
    <select name="algorithm">
      <?php foreach ($allowedCiphers as $alg): ?>
        <?php if ($alg !== 'plain-link'): ?>
        <option value="<?= $alg ?>"><?= strtoupper($alg) ?></option>
        <?php endif; ?>
      <?php endforeach; ?>
    </select><br>

    <button type="submit">Decrypt</button>
    <button type="reset">Reset</button>
  </form>

  <?php if (!empty($output) || !empty($onetimeLink)): ?>
    <?php if (!$isPlainLink && !empty($output)): ?>
      <div class="label">Output:</div>
      <div class="copyable" data-clip="<?= htmlspecialchars($output) ?>"><?= htmlspecialchars($output) ?></div>
    <?php endif; ?>

    <?php if (!empty($generatedKey)): ?>
      <div class="label">Key:</div>
      <div class="copyable" data-clip="<?= $generatedKey ?>"><?= $generatedKey ?></div>

      <div class="label">Algorithm:</div>
      <div class="copyable" data-clip="<?= $algorithmUsed ?>"><?= $algorithmUsed ?></div>

      <div class="warning">
        This content is not stored. Save the key and encrypted text securely. One-time link below is disposable.
      </div>
    <?php endif; ?>

    <?php if (!empty($onetimeLink)): ?>
      <div class="label">One-time Link:</div>
      <div class="copyable" data-clip="<?= $onetimeLink ?>"><?= $onetimeLink ?></div>
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
