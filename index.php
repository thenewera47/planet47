<?php
declare(strict_types=1);
/**
 * Planet 47 Telegram Bot — webhook-ready
 * - Reads BOT_TOKEN from environment (BOT_TOKEN)
 * - Features: /start, /help, /donate, /status, /crypto, /share
 * - Crypto: CoinGecko (USD) + Yahoo USD→INR
 * - Indices: Yahoo finance chart/quote endpoints
 * - Safe users.json storage
 */

date_default_timezone_set('Asia/Kolkata');

$BOT_TOKEN = getenv('BOT_TOKEN') ?: '';
if ($BOT_TOKEN === '') {
    // In web, show a friendly message on GET and exit on webhook.
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        header('Content-Type: text/plain; charset=utf-8');
        echo "BOT_TOKEN is not configured. Please set BOT_TOKEN environment variable.\n";
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
define('DONATE_QR_URL', 'https://i.ibb.co/HHtQpMq/bharatpe-donate-qr.png');
define('BOT_LINK', 'https://t.me/planet47_bot');

/* ----------------------------
   Utilities: HTTP GET (curl)
   ---------------------------- */
function http_get(string $url, int $timeout = 8): ?string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FAILONERROR    => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_USERAGENT      => 'Planet47Bot/1.0 (+https://planet47.replit.app)'
    ]);
    $res = curl_exec($ch);
    if ($res === false) {
        $err = curl_error($ch);
        @file_put_contents(ERROR_LOG, "[" . date('c') . "] HTTP GET error for $url : $err\n", FILE_APPEND);
        curl_close($ch);
        return null;
    }
    curl_close($ch);
    return $res;
}

/* ----------------------------
   Safe JSON file read/write
   ---------------------------- */
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

/* ----------------------------
   Telegram API helpers (JSON POST)
   ---------------------------- */
function tg_api(string $method, array $params = []): ?array {
    $url = API_URL . $method;
    $ch = curl_init($url);
    $payload = json_encode($params);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 10,
    ]);
    $res = curl_exec($ch);
    if ($res === false) {
        @file_put_contents(ERROR_LOG, "[" . date('c') . "] tg_api curl error: " . curl_error($ch) . "\n", FILE_APPEND);
        curl_close($ch);
        return null;
    }
    curl_close($ch);
    $d = json_decode($res, true);
    return is_array($d) ? $d : null;
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

/* ----------------------------
   Small UI helpers
   ---------------------------- */
function flashy(string $t): string { return "✨💫 $t 💫✨"; }
function divider(): string { return "━━━━━━━━━━━━━━━━━━━━━━"; }
function now_ist(): string { return date('d/m/Y H:i') . ' IST'; }

/* ----------------------------
   Data: ensure user & log commands
   ---------------------------- */
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
    if (count($users[$key]['history']) > 50) {
        $users[$key]['history'] = array_slice($users[$key]['history'], -50);
    }
    safe_write_json(USERS_FILE, $users);
}

/* ----------------------------
   Market & Crypto fetchers
   ---------------------------- */

/** getUsdInrRate: Yahoo Finance USDINR=X fallback 84.0 */
function getUsdInrRate(): float {
    $fallback = 84.0;
    $url = "https://query1.finance.yahoo.com/v8/finance/chart/USDINR=X";
    $raw = http_get($url, 6);
    if (!$raw) return $fallback;
    $j = json_decode($raw, true);
    if (!is_array($j)) return $fallback;
    $price = $j['chart']['result'][0]['meta']['regularMarketPrice'] ?? null;
    return is_numeric($price) ? floatval($price) : $fallback;
}

/** getTop10Cryptos: CoinGecko for top 10 usd prices */
function getTop10Cryptos(): array {
    $url = "https://api.coingecko.com/api/v3/coins/markets?vs_currency=usd&order=market_cap_desc&per_page=10&page=1&sparkline=false";
    $raw = http_get($url, 6);
    if (!$raw) return ['Error fetching coin data'];
    $arr = json_decode($raw, true);
    if (!is_array($arr)) return ['Error parsing coin data'];
    $usdInr = getUsdInrRate();
    $lines = [];
    foreach ($arr as $coin) {
        $name = $coin['name'] ?? ($coin['symbol'] ?? 'N/A');
        $symbol = strtoupper($coin['symbol'] ?? '');
        $usd = isset($coin['current_price']) ? floatval($coin['current_price']) : null;
        if ($usd === null) {
            $lines[] = "$name ($symbol): data unavailable";
            continue;
        }
        $inr = $usd * $usdInr;
        $lines[] = sprintf("%s (%s): $%s | ₹%s", $name, $symbol, number_format($usd, 2, '.', ','), number_format($inr, 2, '.', ','));
    }
    return $lines;
}

/** getIndianIndexPrice: Yahoo chart -> regularMarketPrice */
function getIndianIndexPrice(string $symbol): ?float {
    $url = "https://query1.finance.yahoo.com/v8/finance/chart/" . urlencode($symbol);
    $raw = http_get($url, 6);
    if (!$raw) return null;
    $j = json_decode($raw, true);
    if (!is_array($j)) return null;
    $price = $j['chart']['result'][0]['meta']['regularMarketPrice'] ?? null;
    return is_numeric($price) ? floatval($price) : null;
}

/* ----------------------------
   Command implementations
   ---------------------------- */
function cmd_start(int $chat_id) {
    ensure_user($chat_id);
    log_user_command($chat_id, '/start');
    $msg = "🚀 " . flashy("WELCOME TO PLANET 47 BOT") . " 🚀\n\n"
         . "🌎 I’m your <b>Personal Assistant Bot</b> 🤖\n"
         . "💡 Type /help to see what I can do!\n\n"
         . divider() . "\n"
         . "🔗 <a href=\"" . BOT_LINK . "\">" . BOT_LINK . "</a>";
    sendMessage($chat_id, $msg);
}

function cmd_help(int $chat_id) {
    ensure_user($chat_id);
    log_user_command($chat_id, '/help');
    $msg = "📜 " . flashy("COMMANDS LIST") . " 📜\n\n"
         . "/start - 🌟 Welcome message\n"
         . "/help - 📖 Command list\n"
         . "/donate - 💝 Donate via BharatPe\n"
         . "/status - 🟢 Bot status\n"
         . "/crypto - ₿ Top 10 Cryptos (USD / INR)\n"
         . "/share - 📈 Indian Market Indices (latest)\n\n"
         . "⚡ Tip: Type any command anytime to use.";
    sendMessage($chat_id, $msg);
}

function cmd_donate(int $chat_id) {
    ensure_user($chat_id);
    log_user_command($chat_id, '/donate');
    $upi = DONATE_UPI;
    $upiLink = "upi://pay?pa=" . rawurlencode($upi) . "&pn=" . rawurlencode("Planet 47") . "&cu=INR";
    $text = "🙏 " . flashy("SUPPORT PLANET 47") . " 🙏\n\n"
          . "💵 Donate via BharatPe UPI ID:\n<code>$upi</code>\n\n"
          . "🔗 UPI Link: <a href=\"$upiLink\">Pay via UPI</a>\n\n"
          . "📷 QR: " . DONATE_QR_URL . "\n\n"
          . "Thank you for supporting Planet 47!";
    sendMessage($chat_id, $text);
    // Send QR image separately — Telegram will show image
    sendPhoto($chat_id, DONATE_QR_URL, "Scan to donate via BharatPe");
}

function cmd_status(int $chat_id) {
    ensure_user($chat_id);
    log_user_command($chat_id, '/status');
    $msg = "✅ " . flashy("BOT STATUS") . " ✅\n\n"
         . "🟢 Planet 47 Bot is ONLINE & Running 24/7\n"
         . "⚡ Hosted on Render Docker (or your own host)\n"
         . "🕐 Updated: " . now_ist();
    sendMessage($chat_id, $msg);
}

function cmd_crypto(int $chat_id) {
    ensure_user($chat_id);
    log_user_command($chat_id, '/crypto');
    $lines = getTop10Cryptos();
    $body = "₿ " . flashy("TOP 10 CRYPTOS (USD / INR)") . " ₿\n\n" . implode("\n", $lines) . "\n\n" . divider();
    sendMessage($chat_id, $body);
}

function cmd_share(int $chat_id) {
    ensure_user($chat_id);
    log_user_command($chat_id, '/share');
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
        $lines[] = $price === null ? "$name: data unavailable" : sprintf("%s: %s", $name, number_format($price, 2, '.', ','));
    }
    $body = "📈 " . flashy("INDIAN MARKET INDICES — LATEST") . " 📈\n\n" . implode("\n", $lines) . "\n\n" . divider() . "\nUpdated: " . now_ist();
    sendMessage($chat_id, $body);
}

/* ----------------------------
   Router / webhook handling
   ---------------------------- */
try {
    // Allow simple GET health check
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        header('Content-Type: text/plain; charset=utf-8');
        echo "✅ Planet 47 Bot — alive — " . now_ist() . "\n";
        exit;
    }

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

    // message/update handler
    if (isset($update['message'])) {
        $msg = $update['message'];
        $chat_id = intval($msg['chat']['id']);
        $text = (string)($msg['text'] ?? '');
        $cmd = strtolower(trim($text));

        switch ($cmd) {
            case '/start': cmd_start($chat_id); break;
            case '/help':  cmd_help($chat_id);  break;
            case '/donate': cmd_donate($chat_id); break;
            case '/status': cmd_status($chat_id); break;
            case '/crypto': cmd_crypto($chat_id); break;
            case '/share': cmd_share($chat_id); break;
            default:
                if ($text !== '') {
                    sendMessage($chat_id, "❓ Unknown command. Type /help to see available commands.");
                }
        }
    }

    http_response_code(200);
    echo 'OK';
} catch (Throwable $e) {
    @file_put_contents(ERROR_LOG, "[" . date('c') . "] Fatal: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo 'Error';
}
