<?php
// ===============================
// Telegram Bot Config
// ===============================
$BOT_TOKEN = "Place_Your_Token_Here"; // <-- Replace with your Bot Token
$API_URL   = "https://api.telegram.org/bot$BOT_TOKEN/";

// BharatPe UPI
$BHARATPE_UPI = "BHARATPE.8Y0Z0M5P0J89642@fbpe";

// Storage
$USERS_FILE = __DIR__ . "/users.json";
$ERROR_LOG  = __DIR__ . "/error.log";

// ===============================
// JSON Storage Helpers
// ===============================
function load_users() {
    global $USERS_FILE;
    if (!file_exists($USERS_FILE)) return [];
    return json_decode(file_get_contents($USERS_FILE), true) ?? [];
}

function save_users($data) {
    global $USERS_FILE;
    file_put_contents($USERS_FILE, json_encode($data, JSON_PRETTY_PRINT));
}

// ===============================
// Telegram Send Message & Photo
// ===============================
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

function sendPhoto($chat_id, $photo_url, $caption = null) {
    global $API_URL;
    $payload = [
        "chat_id" => $chat_id,
        "photo"   => $photo_url,
    ];
    if ($caption) {
        $payload["caption"] = $caption;
        $payload["parse_mode"] = "HTML";
    }
    file_get_contents($API_URL . "sendPhoto?" . http_build_query($payload));
}

// ===============================
// Command Handler
// ===============================
function processCommand($chat_id, $command) {
    global $BHARATPE_UPI;

    switch ($command) {
        case "/start":
            $msg = "🌟 Welcome to <b>Planet 47 Bot</b>\n\n".
                   "Type /help to see all commands.";
            break;

        case "/help":
            $msg = "📜 <b>COMMANDS LIST</b> 📜\n\n".
                   "/start - 🌟 Welcome message\n".
                   "/help - 📖 Command list\n".
                   "/donate - 💝 Donate via BharatPe\n".
                   "/status - 🟢 Bot status\n".
                   "/crypto - ₿ Top 10 Cryptos (USD & INR)\n".
                   "/share - 📈 Indian Market Indices\n\n".
                   "⚡ Tip: Type any command anytime to use.";
            break;

        case "/donate":
            $msg = "💝 <b>Support Planet 47 Project</b>\n\n".
                   "📌 Pay securely via BharatPe UPI:\n<code>$BHARATPE_UPI</code>\n\n".
                   "📷 Scan this QR to donate:";
            sendMessage($chat_id, $msg);
            // Send BharatPe QR Image (replace with your actual QR if needed)
            sendPhoto($chat_id, "https://i.ibb.co/42yVpG7/bharatpe-qr.png", "🙏 Thank you for supporting us!");
            return;

        case "/status":
            $msg = "🟢 Bot is running smoothly!";
            break;

        case "/crypto":
            $msg = getCryptoPrices();
            break;

        case "/share":
            $msg = getMarketPrices();
            break;

        default:
            $msg = "❌ Unknown command. Type /help";
    }
    sendMessage($chat_id, $msg);
}

// ===============================
// Fetch Crypto Prices
// ===============================
function getCryptoPrices() {
    try {
        $url = "https://api.coingecko.com/api/v3/coins/markets?vs_currency=usd&order=market_cap_desc&per_page=10&page=1&sparkline=false";
        $data = json_decode(file_get_contents($url), true);

        if (!$data) return "⚠️ Error fetching crypto data.";

        $out = "₿ <b>Top 10 Cryptos</b>\n\n";
        foreach ($data as $coin) {
            $usd = number_format($coin["current_price"], 2);
            $inr = number_format($coin["current_price"] * 83, 2); // Approx conversion
            $out .= "🔹 {$coin['name']} ({$coin['symbol']})\n".
                    "💵 USD: \${$usd}\n".
                    "🇮🇳 INR: ₹{$inr}\n\n";
        }
        return $out;
    } catch (Exception $e) {
        return "⚠️ Error fetching crypto data.";
    }
}

// ===============================
// Fetch Indian Market Prices
// ===============================
function getMarketPrices() {
    $markets = [
        "^NSEI" => "NIFTY 50",
        "^NSEBANK" => "BANKNIFTY",
        "NIFTY_FIN_SERVICE.NS" => "FINNIFTY",
        "^NSEMDCP50" => "MIDCAPNIFTY",
        "^BSESN" => "BSE SENSEX",
        "BSEBANK.BO" => "BSE BANKEX"
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

// ===============================
// Main Entry
// ===============================
try {
    $update = json_decode(file_get_contents("php://input"), true);
    if (isset($update["message"])) {
        $chat_id = $update["message"]["chat"]["id"];
        $text = trim($update["message"]["text"]);
        processCommand($chat_id, $text);
    }
} catch (Exception $e) {
    file_put_contents($GLOBALS["ERROR_LOG"], date("Y-m-d H:i:s")." ".$e->getMessage()."\n", FILE_APPEND);
}
