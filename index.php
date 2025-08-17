<?php
// ----------------------------
// Planet 47 Telegram Bot
// ----------------------------

// 🔑 Bot Config
$BOT_TOKEN = "Place_Your_Token_Here";  // <-- replace with your bot token
$API_URL   = "https://api.telegram.org/bot$BOT_TOKEN/";

// 💳 BharatPe UPI
$BHARATPE_UPI = "BHARATPE.8Y0Z0M5P0J89642@fbpe";
$BHARATPE_QR  = "https://github.com/thenewera47/planet47/blob/main/bharatpe-donate-qr.png?raw=true";

// 📁 Storage Paths
$USERS_FILE = __DIR__ . "/users.json";
$ERROR_LOG  = __DIR__ . "/error.log";

// ----------------------------
// JSON Storage
// ----------------------------
function load_users() {
    global $USERS_FILE;
    if (!file_exists($USERS_FILE)) return [];
    return json_decode(file_get_contents($USERS_FILE), true) ?? [];
}

function save_users($data) {
    global $USERS_FILE;
    file_put_contents($USERS_FILE, json_encode($data, JSON_PRETTY_PRINT));
}

// ----------------------------
// Telegram Helpers
// ----------------------------
function sendMessage($chat_id, $text, $keyboard = null) {
    global $API_URL;
    $payload = [
        "chat_id" => $chat_id,
        "text" => $text,
        "parse_mode" => "HTML"
    ];
    if ($keyboard) {
        $payload["reply_markup"] = json_encode($keyboard);
    }
    file_get_contents($API_URL . "sendMessage?" . http_build_query($payload));
}

function sendPhoto($chat_id, $photo_url, $caption = "") {
    global $API_URL;
    $payload = [
        "chat_id" => $chat_id,
        "photo"   => $photo_url,
        "caption" => $caption,
        "parse_mode" => "HTML"
    ];
    file_get_contents($API_URL . "sendPhoto?" . http_build_query($payload));
}

// ----------------------------
// Command Processor
// ----------------------------
function processCommand($chat_id, $command) {
    global $BHARATPE_UPI, $BHARATPE_QR;

    switch ($command) {
        case "/start":
            $msg = "🌟 Welcome to <b>Planet 47 Bot</b>\n\n".
                   "Type /help to see available commands.";
            break;

        case "/help":
            $msg = "📜 <b>COMMANDS LIST</b>\n\n".
                   "👉 /start - Welcome message\n".
                   "👉 /help - Show all commands\n".
                   "👉 /donate - Donate via BharatPe\n".
                   "👉 /status - Bot status\n".
                   "👉 /crypto - Top 10 Cryptos (USD & INR)\n".
                   "👉 /share - Indian Market Indices\n\n".
                   "⚡ You can type these commands anytime!";
            break;

        case "/donate":
            $msg = "💝 <b>Support Planet 47!</b>\n\n".
                   "📌 BharatPe UPI: <code>$BHARATPE_UPI</code>\n\n".
                   "📷 Scan this QR Code to donate:";
            sendMessage($chat_id, $msg);
            sendPhoto($chat_id, $BHARATPE_QR, "🙏 Thank you for your support!");
            return;

        case "/status":
            $msg = "🟢 Bot is running fine!";
            break;

        case "/crypto":
            $msg = getCryptoPrices();
            break;

        case "/share":
            $msg = getMarketPrices();
            break;

        default:
            $msg = "❌ Unknown command. Type /help to see options.";
    }
    sendMessage($chat_id, $msg);
}

// ----------------------------
// Crypto Prices (Top 10)
// ----------------------------
function getCryptoPrices() {
    try {
        $url = "https://api.coingecko.com/api/v3/coins/markets?vs_currency=usd&order=market_cap_desc&per_page=10&page=1&sparkline=false";
        $data = json_decode(file_get_contents($url), true);

        if (!$data) return "⚠️ Could not fetch crypto data.";

        $out = "₿ <b>Top 10 Cryptos</b>\n\n";
        foreach ($data as $coin) {
            $usd = number_format($coin["current_price"], 2);
            $inr = number_format($coin["current_price"] * 83, 2); // Rough conversion
            $out .= "🔹 {$coin['name']} ({$coin['symbol']})\n".
                    "💵 USD: \${$usd}\n".
                    "🇮🇳 INR: ₹{$inr}\n\n";
        }
        return $out;
    } catch (Exception $e) {
        return "⚠️ Error fetching crypto data.";
    }
}

// ----------------------------
// Market Indices
// ----------------------------
function getMarketPrices() {
    $markets = [
        "^NSEI"             => "NIFTY 50",
        "^NSEBANK"          => "BANKNIFTY",
        "NIFTY_FIN_SERVICE.NS" => "FINNIFTY",
        "^NSEMDCP50"        => "MIDCAPNIFTY",
        "^BSESN"            => "BSE SENSEX",
        "BSEBANK.BO"        => "BSE BANKEX"
    ];

    $out = "📈 <b>Indian Market Indices</b>\n\n";
    foreach ($markets as $symbol => $name) {
        $url = "https://query1.finance.yahoo.com/v7/finance/quote?symbols=$symbol";
        $data = json_decode(file_get_contents($url), true);
        $price = $data["quoteResponse"]["result"][0]["regularMarketPrice"] ?? "N/A";
        $out .= "🔹 $name: ₹$price\n";
    }
    return $out;
}

// ----------------------------
// MAIN
// ----------------------------
try {
    $update = json_decode(file_get_contents("php://input"), true);
    if (isset($update["message"])) {
        $chat_id = $update["message"]["chat"]["id"];
        $text    = trim($update["message"]["text"]);
        processCommand($chat_id, $text);
    }
} catch (Exception $e) {
    global $ERROR_LOG;
    file_put_contents($ERROR_LOG, date("Y-m-d H:i:s")." ".$e->getMessage()."\n", FILE_APPEND);
}
