<?php
// includes/botblock.inc.php

function is_bot_request(): bool {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $bot_keywords = [
        'facebookexternalhit', 'Facebot', 'TelegramBot', 'Slackbot',
        'WhatsApp', 'Discordbot', 'Twitterbot', 'LinkedInBot',
        'Daumoa', 'kakaotalk-scrap', 'Yeti', 'NaverBot',
        'Googlebot', 'bingbot', 'AhrefsBot', 'MJ12bot',
        'SemrushBot', 'DotBot', 'python-requests', 'okhttp',
        'Go-http-client', 'curl', 'wget', 'Scrapy', 'Java/'
    ];

    if (!$ua || strlen($ua) < 5) return true;

    foreach ($bot_keywords as $bot) {
        if (stripos($ua, $bot) !== false) return true;
    }

    return false;
}

function block_if_bot(): void {
    if (is_bot_request()) {
        http_response_code(403);
        exit('403 Forbidden');
    }
}
