<?php
// index.php — Planet 47 Telegram Bot (Webhook-ready)
// Requires BOT_TOKEN env var. Deploy to Render and set BOT_TOKEN in Environment.

declare(strict_types=1);
date_default_timezone_set('Asia/Kolkata');

// ---------- CONFIG ----------
$BOT_TOKEN = getenv('BOT_TOKEN') ?: '';
if (!$BOT_TOKEN) {
    http_response_code(500);
    echo "BOT_TOKEN not configured in environment.";
    exit;
}

define('API_URL', 'https://api.telegram.org/bot' . $BOT_TOKEN . '/');
define('USERS_FILE', __DIR__ . '/users.json');
define('ERROR_LOG', __DIR__ . '/error.log');
define('DONATE_UPI', 'BHARATPE.8Y0Z0M5P0J89642@fbpe');
define('DONATE_QR_URL', 'https://github.com/thenewera47/planet47/blob/main/bharatpe-donate-qr.png?raw=true');
define('BOT_LINK', 'https://t.me/planet47_bot');

// ---------- UTIL ----------
function logErr(string $message): void {
    $ts = date('Y-m-d H:i:s');
    @file_put_contents(ERROR_LOG, "[$ts] $message\n", FILE_APPEND);
}

function safeReadJson(string $path): array {
    try {
        if (!file_exists($path)) {
            @file_put_contents($path, json_encode(new stdClass()));
            @chmod($path, 0664);
            return [];
        }
        $raw = file_get_contents($path);
        $arr = json_decode($raw, true);
        return is_array($arr) ? $arr : [];
    } catch (Throwable $e) {
        logErr("safeReadJson: " . $e->getMessage());
        return [];
    }
}

function safeWriteJson(string $path, array $data): bool {
    try {
        $ok = file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        @chmod($path, 0664);
        return $ok !== false;
    } catch (Throwable $e) {
        logErr("safeWriteJson: " . $e->getMessage());
        return false;
    }
}

function apiPost(string $method, array $params = []): ?array {
    $url = API_URL . $method;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $res = curl_exec($ch);
    if ($res === false) {
        logErr("cURL error: " . curl_error($ch));
        curl_close($ch);
        return null;
    }
    curl_close($ch);
    $decoded = json_decode($res, true);
    return is_array($decoded) ? $decoded : null;
}

// ---------- TELEGRAM HELPERS ----------
function sendMessage(int $chat_id, string $text, string $parse = 'Markdown') {
    return apiPost('sendMessage', [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => $parse,
        'disable_web_page_preview' => false
    ]);
}
function sendPhoto(int $chat_id, string $photoUrl, string $caption = '') {
    return apiPost('sendPhoto', [
        'chat_id' => $chat_id,
        'photo' => $photoUrl,
        'caption' => $caption,
        'parse_mode' => 'Markdown'
    ]);
}

// ---------- STYLING ----------
function flashy(string $t): string { return "✨💫 $t 💫✨"; }
function divider(): string { return "━━━━━━━━━━━━━━━━━━━━━━"; }
function now_ist(): string { return date('d/m/Y H:i') . ' IST'; }

// ---------- DATA: users.json ----------
function ensureUser(int $chat_id): void {
    $users = safeReadJson(USERS_FILE);
    if (!isset($users[(string)$chat_id])) {
        $users[(string)$chat_id] = [
            'joined' => time(),
            'history' => []
        ];
        safeWriteJson(USERS_FILE, $users);
    }
}
function logUserCommand(int $chat_id, string $cmd): void {
    $users = safeReadJson(USERS_FILE);
    $key = (string)$chat_id;
    if (!isset($users[$key])) $users[$key] = ['joined' => time(), 'history' => []];
    $users[$key]['history'][] = ['cmd' => $cmd, 'ts' => time()];
    // limit history length
    if (count($users[$key]['history']) > 50) {
        $users[$key]['history'] = array_slice($users[$key]['history'], -50);
    }
    safeWriteJson(USERS_FILE, $users);
}

// ---------- MARKET / CRYPTO FETCHERS ----------

/**
 * getUsdInrRate()
 * Tries Yahoo Finance USDINR=X -> regularMarketPrice
 * Returns float fallback 84.0 on failure.
 */
function getUsdInrRate(): float {
    $fallback = 84.0;
    $url = "https://query1.finance.yahoo.com/v8/finance/chart/USDINR=X";
    $raw = @file_get_contents($url);
    if (!$raw) return $fallback;
    $j = json_decode($raw, true);
    if (!is_array($j)) return $fallback;
    return floatval($j['chart']['result'][0]['meta']['regularMarketPrice'] ?? $fallback);
}

/**
 * getTop10Cryptos()
 * Uses Binance public API for USDT pair prices.
 * Returns array of lines like: "BTC: $12345 | ₹1,037,000"
 */
function getTop10Cryptos(): array {
    // Binance symbols for top 10 popular coins (USDT)
    $symbols = ["BTCUSDT","ETHUSDT","BNBUSDT","XRPUSDT","SOLUSDT","ADAUSDT","DOGEUSDT","TRXUSDT","AVAXUSDT","MATICUSDT"];
    $api = "https://api.binance.com/api/v3/ticker/price?symbol=";
    $lines = [];
    $usdInr = getUsdInrRate();
    foreach ($symbols as $s) {
        $raw = @file_get_contents($api . $s);
        if (!$raw) { $lines[] = "$s: data unavailable"; continue; }
        $j = json_decode($raw, true);
        if (!is_array($j) || !isset($j['price'])) { $lines[] = "$s: data unavailable"; continue; }
        $priceUsd = floatval($j['price']);
        $priceInr = $priceUsd * $usdInr;
        $coin = str_replace("USDT","",$s);
        $lines[] = sprintf("%s: $%s | ₹%s", $coin, number_format($priceUsd, 2, '.', ','), number_format($priceInr, 2, '.', ','));
    }
    return $lines;
}

/**
 * getIndianIndexPrice($symbol)
 * Uses Yahoo finance chart endpoint and returns regularMarketPrice or null.
 */
function getIndianIndexPrice(string $symbol): ?float {
    $url = "https://query1.finance.yahoo.com/v8/finance/chart/" . urlencode($symbol);
    $raw = @file_get_contents($url);
    if (!$raw) return null;
    $j = json_decode($raw, true);
    if (!is_array($j)) return null;
    return isset($j['chart']['result'][0]['meta']['regularMarketPrice']) ? floatval($j['chart']['result'][0]['meta']['regularMarketPrice']) : null;
}

// ---------- COMMAND IMPLEMENTATIONS ----------
function cmd_start(int $chat_id) {
    ensureUser($chat_id);
    logUserCommand($chat_id, '/start');
    $text = "🚀 " . flashy("WELCOME TO PLANET 47 BOT") . " 🚀\n\n"
          . "🌎 I’m your *Personal Assistant Bot* 🤖\n"
          . "💡 Type /help to see what I can do for you!\n\n"
          . divider() . "\n"
          . "🔥 *Your commands are ready to launch!* 🔥";
    sendMessage($chat_id, $text);
}

function cmd_help(int $chat_id) {
    ensureUser($chat_id);
    logUserCommand($chat_id, '/help');
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
    ensureUser($chat_id);
    logUserCommand($chat_id, '/donate');

    $upi = DONATE_UPI;
    $upiLink = "upi://pay?pa=" . urlencode($upi) . "&pn=" . urlencode("Planet 47") . "&cu=INR";
    $text = "🙏 " . flashy("SUPPORT PLANET 47") . " 🙏\n\n"
          . "💵 Donate via BharatPe UPI ID:\n" . "`$upi`" . "\n\n"
          . "🔗 UPI Link: " . $upiLink . "\n\n"
          . "📷 QR: " . DONATE_QR_URL . "\n\n"
          . "🙏 Thank you for supporting Planet 47!";
    // Send message + QR as photo for convenience
    sendMessage($chat_id, $text);
    sendPhoto($chat_id, DONATE_QR_URL, "Scan to donate via BharatPe");
}

function cmd_status(int $chat_id) {
    ensureUser($chat_id);
    logUserCommand($chat_id, '/status');
    $text = "✅ " . flashy("BOT STATUS") . " ✅\n\n"
          . "🟢 Planet 47 Bot is ONLINE & Running 24/7\n"
          . "⚡ Hosted on Render Docker\n"
          . "🕐 Updated: " . now_ist();
    sendMessage($chat_id, $text);
}

function cmd_crypto(int $chat_id) {
    ensureUser($chat_id);
    logUserCommand($chat_id, '/crypto');
    $lines = getTop10Cryptos();
    $body = "₿ " . flashy("TOP 10 CRYPTOS (USD / INR)") . " ₿\n\n" . implode("\n", $lines) . "\n\n" . divider();
    sendMessage($chat_id, $body);
}

function cmd_share(int $chat_id) {
    ensureUser($chat_id);
    logUserCommand($chat_id, '/share');

    // index symbols (Yahoo)
    $indices = [
        "^NSEI" => "NIFTY50",
        "^NSEBANK" => "BANKNIFTY",
        "NIFTY_FIN_SERVICE.NS" => "FINNIFTY",
        "^NSEMDCP50" => "MIDCAPNIFTY",
        "^BSESN" => "BSE SENSEX",
        "BSEBANK.BO" => "BSE BANKEX"
    ];

    $lines = [];
    foreach ($indices as $sym => $name) {
        $price = getIndianIndexPrice($sym);
        if ($price === null) {
            $lines[] = "$name: data unavailable";
        } else {
            $lines[] = sprintf("%s: %s", $name, number_format($price, 2, '.', ','));
        }
    }

    $body = "📈 " . flashy("INDIAN MARKET INDICES — LATEST") . " 📈\n\n" . implode("\n", $lines) . "\n\n" . divider() . "\nUpdated: " . now_ist();
    sendMessage($chat_id, $body);
}

// ---------- ROUTING (webhook) ----------
try {
    // Simple GET health check
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        header('Content-Type: text/plain; charset=utf-8');
        echo "✅ Planet 47 Bot — alive — " . now_ist();
        exit;
    }

    // POST = Telegram webhook
    $raw = file_get_contents('php://input');
    if (!$raw) {
        http_response_code(400);
        echo 'No payload';
        exit;
    }
    $update = json_decode($raw, true);
    if (!is_array($update)) {
        http_response_code(400);
        echo 'Invalid JSON';
        exit;
    }

    // message handler
    if (isset($update['message'])) {
        $msg = $update['message'];
        $chat_id = intval($msg['chat']['id']);
        $text = trim((string)($msg['text'] ?? ''));

        // normalize text lower for matching commands but keep original for logs
        $cmd = strtolower($text);

        switch ($cmd) {
            case '/start': cmd_start($chat_id); break;
            case '/help':  cmd_help($chat_id);  break;
            case '/donate': cmd_donate($chat_id); break;
            case '/status': cmd_status($chat_id); break;
            case '/crypto': cmd_crypto($chat_id); break;
            case '/share': cmd_share($chat_id); break;
            default:
                // ignore non-command messages or reply with help suggestion
                if ($text !== '') {
                    sendMessage($chat_id, "❓ Unknown command. Type /help to see available commands.");
                }
        }
    } else {
        // Unknown update type — ignore
    }

    // respond 200 OK to Telegram
    http_response_code(200);
    echo 'OK';
} catch (Throwable $e) {
    logErr("Runtime error: " . $e->getMessage());
    http_response_code(500);
    echo 'Error';
}


