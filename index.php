<?php
// =============================
// Planet 47 Telegram Bot (PHP)
// =============================

// --- CONFIG ---
define('BOT_TOKEN', 'Place_Your_Token_Here'); 
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');
define('BOT_LINK', 'https://t.me/planet47_bot');
define('DONATE_UPI', 'BHARATPE.8Y0Z0M5P0J89642@fbpe');

// --- HELPERS ---
function flashy($text) {
    return "✨💫 $text 💫✨";
}

function divider() {
    return "━━━━━━━━━━━━━━━━━━━━━━";
}

function sendMessage($chat_id, $text, $parse = "Markdown") {
    $params = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => $parse
    ];
    file_get_contents(API_URL . "sendMessage?" . http_build_query($params));
}

function sendPhoto($chat_id, $photo, $caption = "") {
    $params = [
        'chat_id' => $chat_id,
        'photo' => $photo,
        'caption' => $caption,
        'parse_mode' => "Markdown"
    ];
    file_get_contents(API_URL . "sendPhoto?" . http_build_query($params));
}

// --- COMMAND HANDLERS ---
function cmd_start($chat_id) {
    $msg = "🚀 " . flashy("WELCOME TO PLANET 47 BOT") . " 🚀\n\n"
         . "🌎 I’m your *Personal Assistant Bot* 🤖\n"
         . "💡 Type /help to see what I can do for you!\n\n"
         . divider() . "\n"
         . "🔥 *Your commands are ready to launch!* 🔥";
    sendMessage($chat_id, $msg);
}

function cmd_help($chat_id) {
    $msg = f"📜 " . flashy("COMMANDS LIST") . " 📜\n\n"
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
    $msg = "🙏 " . flashy("SUPPORT PLANET 47") . " 🙏\n\n"
         . "💵 *Donate via BharatPe UPI ID:*\n"
         . "`" . DONATE_UPI . "`\n\n"
         . "📲 *Scan the QR Code below 👇*\n"
         . "🔥 Every contribution keeps this bot alive! 🔥";
    sendMessage($chat_id, $msg);
    sendPhoto($chat_id, "https://i.ibb.co/HHtQpMq/bharatpe-donate-qr.png");
}

function cmd_status($chat_id) {
    $msg = "✅ " . flashy("BOT STATUS") . " ✅\n\n"
         . "🟢 *Planet 47 Bot is ONLINE & Running 24/7!*\n"
         . "⚡ Powered by Replit + UptimeRobot 🚀";
    sendMessage($chat_id, $msg);
}

function cmd_crypto($chat_id) {
    // 🔹 You can replace with real API fetch (like CoinGecko / Binance)
    $msg = "₿ " . flashy("TOP 10 CRYPTOS") . " ₿\n\n"
         . "1. BTC - ₹24,00,000 (BUY → Call)\n"
         . "2. ETH - ₹1,60,000 (SELL → Put)\n"
         . "3. BNB - ₹23,000 (BUY → Call)\n"
         . "4. SOL - ₹7,200 (SELL → Put)\n"
         . "5. XRP - ₹52 (BUY → Call)\n"
         . "6. ADA - ₹30 (SELL → Put)\n"
         . "7. DOGE - ₹6.2 (BUY → Call)\n"
         . "8. TON - ₹520 (SELL → Put)\n"
         . "9. DOT - ₹450 (BUY → Call)\n"
         . "10. TRX - ₹9 (SELL → Put)\n\n"
         . divider() . "\n"
         . "📊 *Signals based on SuperTrend Indicator*";
    sendMessage($chat_id, $msg);
}

function cmd_share($chat_id) {
    $msg = "📈 " . flashy("INDIAN MARKET INDICES") . " 📈\n\n"
         . "• NIFTY50 (^NSEI)\n"
         . "• BANKNIFTY (^NSEBANK)\n"
         . "• FINNIFTY (NIFTY_FIN_SERVICE.NS)\n"
         . "• MIDCAP NIFTY (^NSEMDCP50)\n"
         . "• BSE SENSEX (^BSESN)\n"
         . "• BANKEX (BSE BANKEX)\n\n"
         . divider() . "\n"
         . "⚡ *Live data integration coming soon!*";
    sendMessage($chat_id, $msg);
}

// --- UPDATE HANDLER ---
function processUpdate($update) {
    if (!isset($update['message'])) return;
    $chat_id = $update['message']['chat']['id'];
    $text = trim($update['message']['text'] ?? '');

    switch ($text) {
        case '/start': cmd_start($chat_id); break;
        case '/help': cmd_help($chat_id); break;
        case '/donate': cmd_donate($chat_id); break;
        case '/status': cmd_status($chat_id); break;
        case '/crypto': cmd_crypto($chat_id); break;
        case '/share': cmd_share($chat_id); break;
        default:
            sendMessage($chat_id, "❓ Unknown command. Type /help to see options.");
    }
}

// --- MAIN ENTRY ---
try {
    $data = file_get_contents("php://input");
    if ($data) {
        $update = json_decode($data, true);
        processUpdate($update);
    } else {
        echo "✅ Planet 47 Bot is alive! 🚀";
    }
} catch (Exception $e) {
    echo "Bot crashed: " . $e->getMessage();
}
?>
