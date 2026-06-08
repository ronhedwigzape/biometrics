<?php

function app_flash_html($type, $title, $message) {
    return '<div class="app-flash app-flash-' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . '" role="status">'
        . '<strong>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</strong>'
        . '<span>' . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . '</span>'
        . '<button type="button" class="app-flash-close" aria-label="Dismiss">&times;</button>'
        . '</div>';
}

function app_set_flash($type, $title, $message) {
    $_SESSION['message'] = app_flash_html($type, $title, $message);
}

function app_consume_flash() {
    if(!isset($_SESSION['message'])) {
        return '';
    }

    $flash = $_SESSION['message'];
    unset($_SESSION['message']);
    return $flash;
}

function app_is_ajax_request() {
    $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';

    return strtolower($requestedWith) === 'xmlhttprequest'
        || strpos($accept, 'application/json') !== false;
}

function app_json_response(array $payload, int $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit();
}

