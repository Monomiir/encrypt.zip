<?php
session_start();

require_once __DIR__ . '/includes/botblock.inc.php';
block_if_bot();

function generateRandomKey($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

// 1. Session based limit
function isSessionRateLimited($limitSeconds = 2) {
    $now = time();
    if (isset($_SESSION['last_action']) && $now - $_SESSION['last_action'] < $limitSeconds) {
        return true;
    }
    $_SESSION['last_action'] = $now;
    return false;
}

// 2. IP based limit
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

    if ($data['blocked_until'] > $now) {
        return true;
    }

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

// Merge 1+2 (Hybrid)
function isHybridRateLimited() {
    return isSessionRateLimited(2) || isIpRateLimited(10, 60, 600);
}

$rateLimited = isHybridRateLimited();

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

if (!$rateLimited && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['encrypt_text'])) {
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
                $iv = $ivlen > 0 ? openssl_random_pseudo_bytes($ivlen) : '';
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

            $id = bin2hex(random_bytes(16));
            $subdir = substr($id, 0, 2) . '/' . substr($id, 2, 2);
            $fullPath = __DIR__ . "/messages/$subdir";
            if (!is_dir($fullPath)) mkdir($fullPath, 0700, true);
            $data = json_encode([
                'cipher' => $payload,
                'alg' => $algorithmUsed
            ]);
            file_put_contents("$fullPath/{$id}.json", $data);
            $onetimeLink = "https://encrypt.zip/" . $id;
        }
    } elseif (isset($_POST['decrypt_text'])) {
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
    #keyField .label,
    #rsaField .label {
      margin-top: 5px;
      display: block;
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

<?php if ($rateLimited): ?>
  <div class="label">⚠️ Rate Limit</div>
  <div class="warning">Too many requests. Please wait a moment before trying again.</div>
<?php else: ?>
  <form method="post">
    <div class="label">Encryption</div>
    <textarea name="encrypt_text" rows="5" placeholder="Enter text to encrypt..."></textarea>
    <label class="label">Algorithm:</label>
    <select name="algorithm">
      <?php foreach ($allowedCiphers as $alg): ?>
        <option value="<?= $alg ?>"><?= strtoupper($alg) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit">Encrypt</button>
    <button type="reset">Reset</button>
  </form>

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
    <div id="keyField">
      <label class="label">Decryption Key:</label>
      <input type="text" name="aes_key" id="decrypt_key_input" placeholder="Enter encryption key...">
    </div>
    <div id="rsaField" style="display:none;">
      <label class="label">Private Key:</label>
      <textarea name="rsa_key" rows="10" placeholder="Paste RSA private key here..."></textarea>
    </div>
    <button type="submit">Decrypt</button>
    <button type="reset">Reset</button>
  </form>
<?php endif; ?>

<?php if (!empty($output)): ?>
  <div class="label">Output:</div>
  <div class="copyable" onclick="copyText(this)"><?= htmlspecialchars($output) ?></div>
<?php endif; ?>

<?php if (!empty($generatedKey)): ?>
  <div class="label">Encryption Key:</div>
  <div class="copyable" onclick="copyText(this)"><?= htmlspecialchars($generatedKey) ?></div>
  <div class="warning">⚠️ This encryption key is shown only once and will not be saved on the server.</div>
<?php endif; ?>

<?php if (!empty($algorithmUsed) && !$isPlainLink): ?>
  <div class="label">Algorithm Used:</div>
  <div class="copyable"><?= htmlspecialchars($algorithmUsed) ?></div>
<?php endif; ?>

<?php if (!empty($onetimeLink)): ?>
  <div class="label">One-time Link:</div>
  <div class="copyable" onclick="copyText(this)"><?= htmlspecialchars($onetimeLink) ?></div>
  <div class="warning">⚠️ This link is valid for one-time use only.</div>
<?php endif; ?>

<footer>Powered by monomiir</footer>

<script>
function copyText(el) {
  const copied = el.querySelector('.copied-msg');
  if (copied) copied.remove();
  const span = document.createElement("span");
  span.className = "copied-msg";
  span.innerText = " Copied!";
  el.appendChild(span);
  navigator.clipboard.writeText(el.innerText.replace(/ Copied!$/, ''));
}
window.addEventListener('load', () => {
  document.querySelectorAll('.copyable').forEach(el => {
    el.style.height = 'auto';
    el.style.maxHeight = 'none';
  });
});

function toggleKeyInput(val) {
  const rsa = val.startsWith('rsa');
  document.getElementById('keyField').style.display = rsa ? 'none' : 'block';
  document.getElementById('rsaField').style.display = rsa ? 'block' : 'none';
}
</script>
</body>
</html>
