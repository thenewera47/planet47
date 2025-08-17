<?php
declare(strict_types=1);
/*
  Planet 47 Telegram Bot — webhook-ready
  Expects BOT_TOKEN environment variable.
*/

date_default_timezone_set('Asia/Kolkata');

$BOT_TOKEN = getenv('BOT_TOKEN') ?: '';
if ($BOT_TOKEN === '') {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        header('Content-Type: text/plain; charset=utf-8');
        echo "BOT_TOKEN not configured. Set BOT_TOKEN environment variable.\n";
        exit;
    }
    http_response_code(500);
    echo "BOT_TOKEN not configured.";
    exit;
}

define('API_URL', 'https://api.telegram.org/bot' . $BOT_TOKEN . '/');
define('USERS_FILE', __DIR__ . '/users.json');
define('ERROR_LOG', __DIR__ . '/error.log');
define('DONATE_UPI', 'BHARATPE.8Y0Z0M5P0J89642@fbpe');
define('DONATE_QR_LOCAL', __DIR__ . '/bharatpe-donate-qr.png');
define('DONATE_QR_FALLBACK', 'https://github.com/thenewera47/planet47/blob/main/bharatpe-donate-qr.png?raw=true');
define('BOT_LINK', 'https://t.me/planet47_bot');

/* ===== Simple HTTP GET (curl) ===== */
function http_get(string $url, int $timeout = 8): ?string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_USERAGENT => 'Planet47Bot/1.0'
    ]);
    $res = curl_exec($ch);
    if ($res === false) {
        @file_put_contents(ERROR_LOG, "[".date('c')."] http_get error: " . curl_error($ch) . " URL: $url\n", FILE_APPEND);
        curl_close($ch);
        return null;
    }
    curl_close($ch);
    return $res;
}

/* ===== Safe JSON file helpers ===== */
function safe_read_json(string $path): array {
    if (!file_exists($path)) {
        @file_put_contents($path, json_encode(new stdClass()));
        @chmod($path, 0664);
        return [];
    }
    $raw = @file_get_contents($path);
    if ($raw === false) return [];
    $j = json_decode($raw, true);
    return is_array($j) ? $j : [];
}
function safe_write_json(string $path, array $data): bool {
    $ok = @file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    @chmod($path, 0664);
    return $ok !== false;
}

/* ===== Telegram JSON POST helper ===== */
function tg_api(string $method, array $params = []): ?array {
    $url = API_URL . $method;
    $ch = curl_init($url);
    $payload = json_encode($params);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 10
    ]);
    $res = curl_exec($ch);
    if ($res === false) {
        @file_put_contents(ERROR_LOG, "[".date('c')."] tg_api curl error: " . curl_error($ch) . " method: $method\n", FILE_APPEND);
        curl_close($ch);
        return null;
    }
    curl_close($ch);
    $decoded = json_decode($res, true);
    return is_array($decoded) ? $decoded : null;
}
function sendMessage(int $chat_id, string $text, array $opts = []): ?array {
    $params = array_merge([
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => false
    ], $opts);
    return tg_api('sendMessage', $params);
}
function sendPhoto(int $chat_id, string $photo_url, string $caption = ''): ?array {
    return tg_api('sendPhoto', [
        'chat_id' => $chat_id,
        'photo' => $photo_url,
        'caption' => $caption,
        'parse_mode' => 'HTML'
    ]);
}

/* ===== Helpers ===== */
function flashy(string $t): string { return "✨💫 $t 💫✨"; }
function divider(): string { return "━━━━━━━━━━━━━━━━━━━━━━"; }
function now_ist(): string { return date('d/m/Y H:i') . ' IST'; }

/* ===== User helpers ===== */
function ensure_user(int $chat_id): void {
    $users = safe_read_json(USERS_FILE);
    if (!isset($users[(string)$chat_id])) {
        $users[(string)$chat_id] = ['joined' => time(), 'history' => []];
        safe_write_json(USERS_FILE, $users);
    }
}
function log_user_command(int $chat_id, string $cmd): void {
    $users = safe_read_json(USERS_FILE);
    $key = (string)$chat_id;
    if (!isset($users[$key])) $users[$key] = ['joined' => time(), 'history' => []];
    $users[$key]['history'][] = ['cmd' => $cmd, 'ts' => time()];
    if (count($users[$key]['history']) > 50) $users[$key]['history'] = array_slice($users[$key]['history'], -50);
    safe_write_json(USERS_FILE, $users);
}

/* ===== Market & Crypto fetchers ===== */
function getUsdInrRate(): float {
    $fallback = 84.0;
    $raw = http_get("https://query1.finance.yahoo.com/v8/finance/chart/USDINR=X", 6);
    if (!$raw) return $fallback;
    $j = json_decode($raw, true);
    if (!is_array($j)) return $fallback;
    $price = $j['chart']['result'][0]['meta']['regularMarketPrice'] ?? null;
    return is_numeric($price) ? floatval($price) : $fallback;
}
function getTop10Cryptos(): array {
    $raw = http_get("https://api.coingecko.com/api/v3/coins/markets?vs_currency=usd&order=market_cap_desc&per_page=10&page=1&sparkline=false", 6);
    if (!$raw) return ['Error fetching crypto data'];
    $arr = json_decode($raw, true);
    if (!is_array($arr)) return ['Error parsing crypto data'];
    $fx = getUsdInrRate();
    $lines = [];
    foreach ($arr as $c) {
        $name = $c['name'] ?? 'N/A';
        $sym = strtoupper($c['symbol'] ?? '');
        $usd = isset($c['current_price']) ? floatval($c['current_price']) : null;
        if ($usd === null) { $lines[] = "$name ($sym): data unavailable"; continue; }
        $inr = $usd * $fx;
        $lines[] = sprintf("%s (%s): $%s | ₹%s", $name, $sym, number_format($usd,2,'.',','), number_format($inr,2,'.',','));
    }
    return $lines;
}
function getIndianIndexPrice(string $symbol): ?float {
    $raw = http_get("https://query1.finance.yahoo.com/v8/finance/chart/" . urlencode($symbol), 6);
    if (!$raw) return null;
    $j = json_decode($raw, true);
    if (!is_array($j)) return null;
    $price = $j['chart']['result'][0]['meta']['regularMarketPrice'] ?? null;
    return is_numeric($price) ? floatval($price) : null;
}

/* ===== Commands ===== */
function cmd_start(int $chat_id) {
    ensure_user($chat_id); log_user_command($chat_id, '/start');
    $text = "🚀 " . flashy("WELCOME TO PLANET 47 BOT") . " 🚀\n\n"
          . "🌎 I’m your <b>Personal Assistant Bot</b> 🤖\n"
          . "💡 Type /help to see what I can do!\n\n"
          . divider() . "\n"
          . "🔗 <a href=\"" . BOT_LINK . "\">" . BOT_LINK . "</a>";
    sendMessage($chat_id, $text);
}
function cmd_help(int $chat_id) {
    ensure_user($chat_id); log_user_command($chat_id, '/help');
    $text = "📜 " . flashy("COMMANDS LIST") . " 📜\n\n"
          . "/start - 🌟 Welcome message\n"
          . "/help - 📖 Command list\n"
          . "/donate - 💝 Donate via BharatPe\n"
          . "/status - 🟢 Bot status\n"
          . "/crypto - ₿ Top 10 Cryptos (USD / INR)\n"
          . "/share - 📈 Indian Market Indices (latest)\n\n"
          . "⚡ Tip: Type any command anytime to use.";
    sendMessage($chat_id, $text);
}
function cmd_donate(int $chat_id) {
    ensure_user($chat_id); log_user_command($chat_id, '/donate');
    $upi = DONATE_UPI;
    $upiLink = "upi://pay?pa=" . rawurlencode($upi) . "&pn=" . rawurlencode("Planet 47") . "&cu=INR";
    $text = "🙏 " . flashy("SUPPORT PLANET 47") . " 🙏\n\n"
          . "💵 Donate via BharatPe UPI ID:\n<code>$upi</code>\n\n"
          . "🔗 UPI Link: <a href=\"$upiLink\">Pay via UPI</a>\n\n"
          . "📷 QR below:";
    sendMessage($chat_id, $text);

    // prefer local QR if available
    $qr = file_exists(DONATE_QR_LOCAL) ? (string)DONATE_QR_LOCAL : DONATE_QR_FALLBACK;
    // If local file, Telegram needs multipart upload via files parameter — but tg_api('sendPhoto') supports URL only.
    // So for local file to work, we'd need to use CURL file upload. For simplicity we use fallback URL (GitHub raw) or the hosted path.
    if (file_exists(DONATE_QR_LOCAL)) {
        // If local file, upload as multipart/form-data
        $ch = curl_init(API_URL . 'sendPhoto');
        $cfile = new CURLFile(DONATE_QR_LOCAL);
        $payload = ['chat_id' => $chat_id, 'photo' => $cfile, 'caption' => '🙏 Thank you for supporting Planet 47!'];
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15
        ]);
        $res = curl_exec($ch);
        if ($res === false) @file_put_contents(ERROR_LOG, "[".date('c')."] sendPhoto upload error: ".curl_error($ch)."\n", FILE_APPEND);
        curl_close($ch);
    } else {
        // use URL
        sendPhoto($chat_id, DONATE_QR_FALLBACK, '🙏 Thank you for supporting Planet 47!');
    }
}
function cmd_status(int $chat_id) { ensure_user($chat_id); log_user_command($chat_id, '/status');
    $text = "✅ " . flashy("BOT STATUS") . " ✅\n\n"
          . "🟢 Planet 47 Bot is ONLINE & Running 24/7\n"
          . "⚡ Hosted on Render Docker (or your host)\n"
          . "🕐 Updated: " . now_ist();
    sendMessage($chat_id, $text);
}
function cmd_crypto(int $chat_id) { ensure_user($chat_id); log_user_command($chat_id, '/crypto');
    $lines = getTop10Cryptos();
    $body = "₿ " . flashy("TOP 10 CRYPTOS (USD / INR)") . " ₿\n\n" . implode("\n", $lines) . "\n\n" . divider();
    sendMessage($chat_id, $body);
}
function cmd_share(int $chat_id) { ensure_user($chat_id); log_user_command($chat_id, '/share');
    $indices = [
        "^NSEI" => "NIFTY50",
        "^NSEBANK" => "BANKNIFTY",
        "NIFTY_FIN_SERVICE.NS" => "FINNIFTY",
        "^NSEMDCP50" => "MIDCAPNIFTY",
        "^BSESN" => "BSE SENSEX",
        "BSE-BANK.BO"          => "S&P BSE BANKEX"  // ✅ fixed
    ];
    $lines = [];
    foreach ($indices as $sym => $name) {
        $p = getIndianIndexPrice($sym);
        $lines[] = $p === null ? "$name: data unavailable" : sprintf("%s: %s", $name, number_format($p, 2, '.', ','));
    }
    $body = "📈 " . flashy("INDIAN MARKET INDICES — LATEST") . " 📈\n\n" . implode("\n", $lines) . "\n\n" . divider() . "\nUpdated: " . now_ist();
    sendMessage($chat_id, $body);
}

/* ===== Router / Webhook Handler ===== */
try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        header('Content-Type: text/plain; charset=utf-8');
        echo "Planet 47 Bot — alive — " . now_ist() . "\n";
        exit;
    }
    $raw = file_get_contents('php://input');
    if (!$raw) { http_response_code(400); echo 'No payload'; exit; }
    $update = json_decode($raw, true);
    if (!is_array($update)) { http_response_code(400); echo 'Invalid JSON'; exit; }

    if (isset($update['message'])) {
        $msg = $update['message'];
        $chat_id = intval($msg['chat']['id']);
        $text = (string)($msg['text'] ?? '');
        $cmd = strtolower(trim($text));
        switch ($cmd) {
            case '/start': cmd_start($chat_id); break;
            case '/help': cmd_help($chat_id); break;
            case '/donate': cmd_donate($chat_id); break;
            case '/status': cmd_status($chat_id); break;
            case '/crypto': cmd_crypto($chat_id); break;
            case '/share': cmd_share($chat_id); break;
            default:
                if ($text !== '') sendMessage($chat_id, "❓ Unknown command. Type /help.");
        }
    }
    http_response_code(200); echo 'OK';
} catch (Throwable $e) {
    @file_put_contents(ERROR_LOG, "[".date('c')."] Fatal: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500); echo 'Error';
}


