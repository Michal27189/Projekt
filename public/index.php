<?php
// 1. Sesja i wspólne ustawienia.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
set_exception_handler(function (Throwable $blad) {
    error_log((string) $blad);
    http_response_code(500);
    exit('Błąd serwera. Sprawdź log PHP i konfigurację bazy.');
});
session_start(['use_strict_mode' => true, 'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
require __DIR__ . '/baza.php';

// Dwie funkcje: bezpieczny tekst z formularza i bezpieczny tekst do HTML.
function pole($nazwa) {
    return isset($_POST[$nazwa]) && is_string($_POST[$nazwa]) ? $_POST[$nazwa] : '';
}
function h($tekst) {
    return htmlspecialchars((string) $tekst, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
if (empty($_SESSION['token'])) $_SESSION['token'] = bin2hex(random_bytes(32));

// 2. Aktualne konto z bazy.
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
$role = ['client' => 'klient', 'employee' => 'pracownik', 'admin' => 'administrator'];
$strona = isset($_GET['strona']) && is_string($_GET['strona']) ? $_GET['strona'] : ($user ? ($role[$user['role']] ?? 'logowanie') : 'logowanie');
$dozwolone = ['logowanie', 'rejestracja', 'profil', 'klient', 'pracownik', 'administrator'];
if (!in_array($strona, $dozwolone, true)) { http_response_code(404); exit('Nie ma takiej strony.'); }

// 3. Ochrona stron przed wyświetleniem HTML.
if (in_array($strona, ['profil', 'klient', 'pracownik', 'administrator'], true)) {
    if (!$user) { header('Location: index.php?strona=logowanie'); exit; }
    if ($strona !== 'profil' && $strona !== ($role[$user['role']] ?? '')) {
        http_response_code(403); exit('Brak uprawnień.');
    }
}
if ($user && in_array($strona, ['logowanie', 'rejestracja'], true)) {
    header('Location: index.php?strona=' . $role[$user['role']]); exit;
}
$bledy = [];
$imie = $user['name'] ?? '';
$nazwisko = $user['surname'] ?? '';
$telefon = $user['phone'] ?? '';
$email = '';

// 4. Obsługa formularzy. Wylogowanie.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['token'], pole('token'))) {
        http_response_code(403); exit('Nieprawidłowy formularz. Odśwież stronę.');
    }
    if (pole('akcja') === 'wyloguj') {
        $_SESSION = [];
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        session_destroy();
        header('Location: index.php?strona=logowanie'); exit;
    }
    if ($strona === 'logowanie') {
        $email = trim(pole('email'));
        $q = $pdo->prepare('SELECT id, password, role, active FROM users WHERE email = ?');
        $q->execute([$email]);
        $konto = $q->fetch();
        if ($konto && $konto['active'] && isset($role[$konto['role']]) && password_verify(pole('haslo'), $konto['password'])) {
            session_regenerate_id(true);
            $_SESSION['id'] = (int) $konto['id'];
            $_SESSION['token'] = bin2hex(random_bytes(32));
            header('Location: index.php?strona=' . $role[$konto['role']]); exit;
        }
        $bledy[] = 'Błędny e-mail lub hasło albo nieaktywne konto.';
    }
    // Rejestracja i profil mają te same pola osobowe
    if ($strona === 'rejestracja' || $strona === 'profil') {
        $imie = trim(pole('imie')); $nazwisko = trim(pole('nazwisko')); $telefon = trim(pole('telefon'));
        if ($imie === '' || mb_strlen($imie) > 50) $bledy[] = 'Imię: od 1 do 50 znaków.';
        if ($nazwisko === '' || mb_strlen($nazwisko) > 50) $bledy[] = 'Nazwisko: od 1 do 50 znaków.';
        if (!preg_match('/^[0-9+ ()-]{6,20}$/', $telefon)) $bledy[] = 'Telefon: 6–20 znaków (cyfry, spacje, +, -, nawiasy).';
    }
    if ($strona === 'rejestracja') {
        $email = trim(pole('email')); $haslo = pole('haslo');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) $bledy[] = 'Wpisz poprawny e-mail, do 100 znaków.';
        if (strlen($haslo) < 8 || strlen($haslo) > 72 || strpos($haslo, "\0") !== false) $bledy[] = 'Hasło: 8–72 bajty, bez znaku zerowego. Polskie znaki zajmują więcej bajtów.';
        if ($haslo !== pole('powtorz')) $bledy[] = 'Hasła są różne.';
        if (!$bledy) {
            $q = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $q->execute([$email]);
            if ($q->fetch()) $bledy[] = 'E-mail jest już zajęty.';
        }
        if (!$bledy) {
            try {
                $q = $pdo->prepare("INSERT INTO users (name, surname, phone, email, password, role, active) VALUES (?, ?, ?, ?, ?, 'client', 1)");
                $q->execute([$imie, $nazwisko, $telefon, $email, password_hash($haslo, PASSWORD_BCRYPT)]);
                $_SESSION['sukces'] = 'Konto utworzone. Zaloguj się.';
                header('Location: index.php?strona=logowanie'); exit;
            } catch (PDOException $blad) {
                if ($blad->getCode() === '23000') $bledy[] = 'Nie można utworzyć konta. E-mail może być zajęty.';
                else throw $blad;
            }
        }
    }
    if ($strona === 'profil' && !$bledy) {
        // ID właściciela z sesji, nigdy z formularza ani URL.
        $q = $pdo->prepare('UPDATE users SET name = ?, surname = ?, phone = ? WHERE id = ?');
        $q->execute([$imie, $nazwisko, $telefon, $user['id']]);
        $_SESSION['sukces'] = 'Zapisano zmiany.';
        header('Location: index.php?strona=profil'); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wypożyczalnia - <?= h($strona) ?></title>
    <link rel="stylesheet" href="../css/styl.css">
</head>
<body>
    <main>
        <h1>Wypożyczalnia sprzetu komputerowego</h1>

        <?php if($user): ?>
            <p>
                <a href="index.php">Mój panel</a>
                <a href="?strona=profil">Mój profil</a>
            </p>

            <form method="post">
                <input type="hidden" name="token" value="<?= h($_SESSION['token']) ?>">
                <button name="akcja" value="wyloguj">Wyloguj się</button>
            </form>
        <?php else: ?>
            <p>
                <a href="?strona=logownaie">Logownaie</a>
                <a href="?strona=rejestracja">Rejestracja</a>
            </p>
        <?php endif; ?>

        <?php foreach ($bledy as $blad): ?>
            <p class="blad"><?= h($blad) ?></p>
        <?php endforeach; ?>

        <?php if (isset($_SESSION['sukces'])): ?>
            <p class="sukces"><?= h($_SESSION['sukces']) ?></p>
            <?php unset($_SESSION['sukces']); ?>
        <?php endif; ?>

        <?php if (in_array($strona, ['logowanie', 'rejestracja', 'profil'], true)): ?>
            <h2>
                <?= h([
                    'logowanie'  => 'Logowanie',
                    'rejestracja' => 'Rejestracja',
                    'profil'     => 'Mój profil',
                ][$strona]) ?>
            </h2>

            <?php if ($strona === 'profil'): ?>
                <p>E-mail: <?= h($user['email']) ?></p>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="token" value="<?= h($_SESSION['token']) ?>">

                <?php if ($strona !== 'logowanie'): ?>
                    <label>
                        Imię
                        <input name="imie" maxlength="50" value="<?= h($imie) ?>"required>
                    </label>

                    <label>
                        Nazwisko
                        <input name="nazwisko" maxlength="50" value="<?= h($nazwisko) ?>"required>
                    </label>

                    <label>
                        Telefon
                        <input name="telefon" maxlength="20" value="<?= h($telefon) ?>"required>
                    </label>
                <?php endif; ?>

                <?php if ($strona !== 'profil'): ?>
                    <label>
                        E-mail
                        <input name="email" maxlength="50" value="<?= h($email) ?>"required>
                    </label>

                    <label>
                        Hasło
                        <input name="haslo" maxlength="50" value="<?= h($email) ?>"required>
                    </label>
                <?php endif; ?>

                <?php if ($strona === 'rejestracja'): ?>
                    <label>
                        Powtórz hasło
                        <input name="powtorz" type="password" required>
                    </label>
                <?php endif; ?>

                <button>
                    <?= $strona === 'logowanie'
                        ? 'Zaloguj się'
                        : ($strona === 'rejestracja' ? 'Utwórz konto' : 'Zapisz zmiany') ?>
                </button>
            </form>
        <?php else: ?>
            <h2>Panel: <?= h($strona) ?></h2>
            <p>Witaj, <?= h($user['name'] . ' ' . $user['surname']) ?>!</p>
            <p>Panel jest chroniony. Funkcje zarządzania i rezerwacji zostaną dodane w kolejnych etapach.</p>
        <?php endif; ?>
    </main>
</body>
</html>
