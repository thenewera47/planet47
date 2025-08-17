<?php
// Telegram Bot Config
$BOT_TOKEN = 'Place_Your_Token_Here');
$API_URL   = "https://api.telegram.org/bot$BOT_TOKEN/";

// BharatPe UPI ID
$BHARATPE_UPI = "BHARATPE.8Y0Z0M5P0J89642@fbpe";

// Storage
$USERS_FILE = __DIR__ . "/users.json";
$ERROR_LOG  = __DIR__ . "/error.log";

// Load JSON safely
function load_users() {
    global $USERS_FILE;
    if (!file_exists($USERS_FILE)) return [];
    return json_decode(file_get_contents($USERS_FILE), true) ?? [];
}

// Save JSON safely
function save_users($data) {
    global $USERS_FILE;
    file_put_contents($USERS_FILE, json_encode($data, JSON_PRETTY_PRINT));
}

// Telegram Send Message
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

// Handle commands
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
            $msg = "💝 Support the project!\n\n".
                   "📌 Pay using BharatPe UPI:\n<code>$BHARATPE_UPI</code>\n\n".
                   "📷 Scan this QR to donate:";
            sendMessage($chat_id, $msg);
            // Send QR image (example, replace with your hosted QR image URL)
            file_get_contents("https://api.telegram.org/bot".BOT_TOKEN."/sendPhoto?chat_id=$chat_id&photo=https://i.ibb.co/42yVpG7/bharatpe-qr.png");
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

// Get Crypto Prices (Top 10 in USD/INR)
function getCryptoPrices() {
    try {
        $url = "https://api.coingecko.com/api/v3/coins/markets?vs_currency=usd&order=market_cap_desc&per_page=10&page=1&sparkline=false";
        $data = json_decode(file_get_contents($url), true);

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

// Get Indian Market Prices
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

// --- Main ---
try {
    $update = json_decode(file_get_contents("php://input"), true);
    if (isset($update["message"])) {
        $chat_id = $update["message"]["chat"]["id"];
        $text = trim($update["message"]["text"]);
        processCommand($chat_id, $text);
    }
} catch (Exception $e) {
    file_put_contents($ERROR_LOG, date("Y-m-d H:i:s")." ".$e->getMessage()."\n", FILE_APPEND);
}

