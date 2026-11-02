<?php
// Wspólny początek stron: sesja, połączenie z bazą, funkcje pomocnicze i zalogowany użytkownik.
date_default_timezone_set('Europe/Warsaw');
ini_set('display_errors', '0');
ini_set('log_errors', '1');
set_exception_handler(function (Throwable $blad) {
    error_log((string) $blad);
    http_response_code(500);
    exit('Błąd serwera. Sprawdź log PHP i konfigurację bazy.');
});
session_start([
    'use_strict_mode' => true,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
require __DIR__ . '/baza.php';

// Dwie funkcje: bezpieczny tekst z formularza i bezpieczny tekst do HTML.
function pole($nazwa)
{
    return isset($_POST[$nazwa]) && is_string($_POST[$nazwa]) ? $_POST[$nazwa] : '';
}

function h($tekst)
{
    return htmlspecialchars((string) $tekst, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

if (empty($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}

// Aktualne konto z bazy. Rola nigdy nie pochodzi z formularza.
$user = null;
if (!empty($_SESSION['id'])) {
    $q = $pdo->prepare('SELECT id, name, surname, email, phone, role, active FROM users WHERE id = ?');
    $q->execute([$_SESSION['id']]);
    $user = $q->fetch();
    if (!$user || !$user['active']) {
        $user = null;
        unset($_SESSION['id']);
    }
}
