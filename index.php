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
    'plain-link',
    'aes-128-cbc', 'aes-192-cbc', 'aes-256-cbc',
    'aes-128-ctr', 'aes-192-ctr', 'aes-256-ctr',
    'aes-128-ecb', 'aes-192-ecb', 'aes-256-ecb',
    'aes-128-gcm', 'aes-192-gcm', 'aes-256-gcm',
    'rsa-1024', 'rsa-2048'
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
            } elseif (str_starts_with($cipher, 'rsa-')) {
                $bits = $cipher === 'rsa-2048' ? 2048 : 1024;
                $res = openssl_pkey_new([
                    "private_key_bits" => $bits,
                    "private_key_type" => OPENSSL_KEYTYPE_RSA,
                ]);
                openssl_pkey_export($res, $privateKey);
                $details = openssl_pkey_get_details($res);
                $publicKey = $details["key"];
                openssl_public_encrypt($text, $encrypted, $publicKey);
                $payload = base64_encode($encrypted);
                $generatedKey = $privateKey;
                $output = $payload;
            } else {
                $key = generateRandomKey(32);
                $ivlen = openssl_cipher_iv_length($cipher);
                $iv = openssl_random_pseudo_bytes($ivlen);
                if (str_contains($cipher, 'gcm')) {
                    $tag = '';
                    $encrypted = openssl_encrypt($text, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag);
                    $payload = base64_encode($iv . "::" . $encrypted . "::" . $tag);
                } else {
                    $encrypted = openssl_encrypt($text, $cipher, $key, 0, $iv);
                    $payload = base64_encode($iv . "::" . $encrypted);
                }
                if ($encrypted === false) {
                    $output = 'Encryption failed.';
                } else {
                    $output = $payload;
                    $generatedKey = $key;
                }
            }
            $id = bin2hex(random_bytes(8));
            $data = json_encode([
                'cipher' => $payload,
                'key' => $generatedKey,
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
        $key = str_starts_with($cipher, 'rsa-') ? ($_POST['rsa_key'] ?? '') : ($_POST['aes_key'] ?? '');

        if (!in_array($cipher, $allowedCiphers) || $cipher === 'plain-link') {
            $output = 'Unsupported algorithm.';
        } elseif (str_starts_with($cipher, 'rsa-')) {
            $encrypted = base64_decode($ciphertext);
            $success = openssl_private_decrypt($encrypted, $decrypted, $key);
            $output = $success ? $decrypted : 'Decryption failed.';
        } else {
            if (strlen($key) < 8) {
                $output = 'Decryption key must be at least 8 characters.';
            } else {
                $decoded = base64_decode($ciphertext);
                if (str_contains($cipher, 'gcm')) {
                    $parts = explode("::", $decoded);
                    if (count($parts) === 3) {
                        list($iv, $encrypted, $tag) = $parts;
                        $decrypted = openssl_decrypt($encrypted, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag);
                    } else {
                        $decrypted = false;
                    }
                } else {
                    $parts = explode("::", $decoded);
                    if (count($parts) === 2) {
                        list($iv, $encrypted) = $parts;
                        $decrypted = openssl_decrypt($encrypted, $cipher, $key, 0, $iv);
                    } else {
                        $decrypted = false;
                    }
                }
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
  <title>encrypt.zip - Online Text Encrypter</title>
  <link rel="icon" href="/assets/favicon.png" type="image/png">

  <!-- Open Graph -->
  <meta property="og:title" content="encrypt.zip - Online Text Encrypter">
  <meta property="og:description" content="Encrypt messages with AES or RSA and generate secure one-time links">
  <meta property="og:image" content="/assets/og-image.png">
  <meta property="og:url" content="https://encrypt.zip">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body {
      background: #000;
      color: #ccc;
      font-family: 'Courier New', monospace;
      margin: 0;
      padding: 20px;
    }
    h1 {
      text-align: center;
      font-size: 32px;
      margin-bottom: 40px;
      color: #0f0;
    }
    .label {
      margin-top: 20px;
      font-weight: bold;
    }
    textarea, input[type="text"], select {
      width: 100%;
      max-width: 100%;
      box-sizing: border-box;
      padding: 10px;
      font-family: monospace;
      background: #111;
      border: 1px solid #555;
      color: #ccc;
      margin-top: 5px;
      resize: vertical;
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
      overflow-x: auto;
      box-sizing: border-box;
    }
    .copied-msg {
      color: #0f0;
      margin-left: 5px;
      font-weight: normal;
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

  <!-- Encryption Form -->
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

  <!-- Decryption Form -->
  <form method="post">
    <div class="label">Decryption</div>
    <textarea name="decrypt_text" rows="5" placeholder="Enter encrypted text..."></textarea>

    <label class="label">Algorithm:</label>
    <select name="algorithm" id="dec_algo" onchange="toggleKeyInput(this.value)">
      <?php foreach ($allowedCiphers as $alg): ?>
        <?php if ($alg !== 'plain-link'): ?>
          <option value="<?= $alg ?>"><?= strtoupper($alg) ?></option>
        <?php endif; ?>
      <?php endforeach; ?>
    </select>

    <!-- AES Key Field -->
    <div id="keyField">
      <label class="label">Decryption Key:</label>
      <input type="text" name="aes_key" id="decrypt_key_input" placeholder="Enter encryption key...">
    </div>

    <!-- RSA Private Key Field -->
    <div id="rsaField" style="display:none;">
      <label class="label">Private Key:</label>
      <textarea name="rsa_key" rows="10" placeholder="Paste RSA private key here..."></textarea>
    </div>

    <button type="submit">Decrypt</button>
    <button type="reset">Reset</button>
  </form>

  <!-- Output Area -->
  <?php if (!empty($output)): ?>
    <div class="label">Output:</div>
    <div class="copyable" onclick="copyText(this)"><?= htmlspecialchars($output) ?></div>
  <?php endif; ?>

  <?php if (!empty($generatedKey)): ?>
    <div class="label">Encryption Key:</div>
    <div class="copyable" onclick="copyText(this)"><?= htmlspecialchars($generatedKey) ?></div>
  <?php endif; ?>

  <?php if (!empty($algorithmUsed) && !$isPlainLink): ?>
    <div class="label">Algorithm Used:</div>
    <div class="copyable"><?= htmlspecialchars($algorithmUsed) ?></div>
  <?php endif; ?>

  <?php if (!empty($onetimeLink)): ?>
    <div class="label">One-time Link:</div>
    <div class="copyable" onclick="copyText(this)"><?= htmlspecialchars($onetimeLink) ?></div>
    <?php if (!$isPlainLink): ?>
      <div class="warning">⚠️ This link can only be accessed once. Encryption key is not stored on the server. Save the output securely.</div>
    <?php endif; ?>
  <?php endif; ?>

  <footer>
    Powered by monomiir
  </footer>

  <script>
    function copyText(el) {
      const copiedSpan = el.querySelector(".copied-msg");
      if (copiedSpan) copiedSpan.remove();

      const text = el.innerText.replace(/\\s*Copied!$/, '');
      navigator.clipboard.writeText(text).then(() => {
        const span = document.createElement("span");
        span.className = "copied-msg";
        span.innerText = " Copied!";
        el.appendChild(span);
      });
    }

    function toggleKeyInput(algo) {
      const rsa = algo.startsWith('rsa');
      document.getElementById('keyField').style.display = rsa ? 'none' : 'block';
      document.getElementById('rsaField').style.display = rsa ? 'block' : 'none';
    }
  </script>
</body>
</html>
