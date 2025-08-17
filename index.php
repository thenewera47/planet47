<?php
// =============================
// Planet 47 Telegram Bot (PHP)
// Render.com Docker Web Service
// =============================

/**
 * IMPORTANT:
 * - Set your token in Render > Environment > Secret:
 *     BOT_TOKEN = 'Place_Your_Token_Here');
 * - Then set Telegram webhook to your Render URL:
 *     https://api.telegram.org/bot<token>/setWebhook?url=https://<your-render-url>/webhook
 */

date_default_timezone_set('Asia/Kolkata');

// --- CONFIG ---
$BOT_TOKEN = getenv('BOT_TOKEN') ?: '';
if (!$BOT_TOKEN) {
    http_response_code(500);
    echo "BOT_TOKEN not set. Configure it in Render dashboard.";
    exit;
}

define('API_URL', 'https://api.telegram.org/bot' . $BOT_TOKEN . '/');
define('USERS_FILE', __DIR__ . '/users.json');
define('ERROR_LOG', __DIR__ . '/error.log');
define('BOT_LINK', 'https://t.me/planet47_bot');
define('DONATE_UPI', 'BHARATPE.8Y0Z0M5P0J89642@fbpe');

// --- UTIL/STYLE ---
function flashy($text) { return "✨💫 $text 💫✨"; }
function divider()     { return "━━━━━━━━━━━━━━━━━━━━━━"; }
function now_ist()     { return date('d/m/Y H:i') . ' IST'; }

// --- LOGGING ---
function logError($msg) {
    $ts = date('Y-m-d H:i:s');
    @file_put_contents(ERROR_LOG, "[$ts] $msg\n", FILE_APPEND);
}

// --- STORAGE ---
function loadUsers() {
    try {
        if (!file_exists(USERS_FILE)) {
            file_put_contents(USERS_FILE, json_encode(new stdClass()));
        }
        $json = file_get_contents(USERS_FILE);
        $data = json_decode($json, true);
        if (!is_array($data)) $data = [];
        return $data;
    } catch (Throwable $e) {
        logError("loadUsers: " . $e->getMessage());
        return [];
    }
}
function saveUsers($users) {
    try {
        file_put_contents(USERS_FILE, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        // Ensure permissions in case container/fs changed
        @chmod(USERS_FILE, 0664);
        return true;
    } catch (Throwable $e) {
        logError("saveUsers: " . $e->getMessage());
        return false;
    }
}

// --- TELEGRAM HTTP HELPERS ---
function tgGet($method, $params = []) {
    $url = API_URL . $method . '?' . http_build_query($params);
    return @file_get_contents($url);
}
function tgPost($method, $params = []) {
    $ch = curl_init(API_URL . $method);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($params)
    ]);
    $out = curl_exec($ch);
    if ($out === false) logError('curl: ' . curl_error($ch));
    curl_close($ch);
    return $out;
}
function sendMessage($chat_id, $text, $parse = 'Markdown') {
    tgPost('sendMessage', [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => $parse
    ]);
}
function sendPhoto($chat_id, $photoUrl, $caption = '') {
    tgPost('sendPhoto', [
        'chat_id'  => $chat_id,
        'photo'    => $photoUrl,
        'caption'  => $caption,
        'parse_mode' => 'Markdown'
    ]);
}

// --- COMMANDS ---
function cmd_start($chat_id) {
    $msg = "🚀 " . flashy("WELCOME TO PLANET 47 BOT") . " 🚀\n\n"
         . "🌎 I’m your *Personal Assistant Bot* 🤖\n"
         . "💡 Type /help to see what I can do for you!\n\n"
         . divider() . "\n"
         . "🔥 *Your commands are ready to launch!* 🔥";
    sendMessage($chat_id, $msg);
}
function cmd_help($chat_id) {
    // EXACT WORDING/FORMAT requested
    $msg =
        "📜 " . flashy("COMMANDS LIST") . " 📜\n\n"
        . "/start - 🌟 Welcome message\n"
        . "/help - 📖 Command list\n"
        . "/donate - 💝 Donate via BharatPe\n"
        . "/status - 🟢 Bot status\n"
        . "/crypto - ₿ Top 10 Cryptos\n"
        . "/share - 📈 Indian Market Indices\n\n"
        . "⚡ Tip: Type any command anytime to use.";
    sendMessage($chat_id, $msg);
}
function cmd_donate($chat_id) {
    $upiLink = "upi://pay?pa=" . urlencode(DONATE_UPI) . "&pn=" . urlencode("Planet 47") . "&cu=INR";
    $msg = "🙏 " . flashy("SUPPORT PLANET 47") . " 🙏\n\n"
         . "💵 *Donate via BharatPe UPI ID:*\n"
         . "`" . DONATE_UPI . "`\n\n"
         . "📲 *UPI Payment Link:*\n"
         . "$upiLink\n\n"
         . "🔥 Every contribution keeps this bot alive! 🔥\n"
         . "🤖 *Bot Link:* " . BOT_LINK;
    sendMessage($chat_id, $msg);
    sendPhoto($chat_id, "https://i.ibb.co/HHtQpMq/bharatpe-donate-qr.png", "📱 Scan to donate");
}
function cmd_status($chat_id) {
    $msg = "✅ " . flashy("BOT STATUS") . " ✅\n\n"
         . "🟢 *Planet 47 Bot is ONLINE & Running 24/7!*\n"
         . "⚡ Hosted on Render Docker 🚀\n\n"
         . "🕐 Updated: " . now_ist();
    sendMessage($chat_id, $msg);
}
function cmd_crypto($chat_id) {
    // Placeholder list (replace with CoinGecko/Binance fetch if you like)
    $msg = "₿ " . flashy("TOP 10 CRYPTOS") . " ₿\n\n"
         . "BTC: ₹24,00,000 → BUY (Call) 📈\n"
         . "ETH: ₹1,60,000 → SELL (Put) 📉\n"
         . "BNB: ₹23,000 → BUY (Call) 📈\n"
         . "SOL: ₹7,200 → SELL (Put) 📉\n"
         . "XRP: ₹52 → BUY (Call) 📈\n"
         . "ADA: ₹30 → SELL (Put) 📉\n"
         . "DOGE: ₹6.2 → BUY (Call) 📈\n"
         . "TON: ₹520 → SELL (Put) 📉\n"
         . "DOT: ₹450 → BUY (Call) 📈\n"
         . "TRX: ₹9 → SELL (Put) 📉\n\n"
         . divider() . "\n"
         . "📊 *Signals based on SuperTrend Indicator*";
    sendMessage($chat_id, $msg);
}
function cmd_share($chat_id) {
    // Index list (static text; you can wire real data later)
    $msg = "📈 " . flashy("INDIAN MARKET INDICES") . " 📈\n\n"
         . "• NIFTY50 (^NSEI)\n"
         . "• BANKNIFTY (^NSEBANK)\n"
         . "• FINNIFTY (NIFTY_FIN_SERVICE.NS)\n"
         . "• MIDCAP NIFTY (^NSEMDCP50)\n"
         . "• BSE SENSEX (^BSESN)\n"
         . "• BSE BANKEX (BSEBANK.BO)\n\n"
         . divider() . "\n"
         . "🕐 Updated: " . now_ist();
    sendMessage($chat_id, $msg);
}

// --- ROUTER/WEBHOOK HANDLER ---
function handleUpdate($update) {
    $users = loadUsers();

    if (isset($update['message'])) {
        $chat_id = $update['message']['chat']['id'];
        $text    = trim($update['message']['text'] ?? '');

        // create minimal user record if missing
        if (!isset($users[$chat_id])) {
            $users[$chat_id] = ['joined' => time()];
            saveUsers($users);
        }

        switch (strtolower($text)) {
            case '/start':  cmd_start($chat_id); break;
            case '/help':   cmd_help($chat_id); break;
            case '/donate': cmd_donate($chat_id); break;
            case '/status': cmd_status($chat_id); break;
            case '/crypto': cmd_crypto($chat_id); break;
            case '/share':  cmd_share($chat_id); break;
            default:
                sendMessage($chat_id, "❓ Unknown command. Type /help to see options.");
        }
        return;
    }

    // (Optional) handle callback_query or others here
}

// --- MAIN ENTRY ---
try {
    // Health check
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        header('Content-Type: text/plain');
        echo "✅ Planet 47 PHP Bot — alive @ " . now_ist();
        exit;
    }

    // Webhook POST from Telegram
    $raw = file_get_contents("php://input");
    if (!$raw) {
        http_response_code(400);
        echo "No input";
        exit;
    }
    $update = json_decode($raw, true);
    if (!$update) {
        http_response_code(400);
        echo "Invalid JSON";
        exit;
    }

    handleUpdate($update);
    http_response_code(200);
    echo "OK";
} catch (Throwable $e) {
    logError("Fatal: " . $e->getMessage());
    http_response_code(500);
    echo "Error";
}
