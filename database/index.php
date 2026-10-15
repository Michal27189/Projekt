<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wypożyczalnia - <?= h($strona) ?></title>
    <link rel="stylesheet" herf="../css/styl.css">
</head>
<body>
    <main>
        <h1>Wypożyczalnia sprzetu komputerowego</h1>

        <?php if($user): ?>
            <p>
                <a herf="index.php">Mój panel</a>
                <a herf="?strona=profil">Mój profil</a>
            </p>

            <form method="post">
                <input type="hidden" name="token" value="<?= h($_SESSION['token']) ?>">
                <botton name="akcja" value="wyloguj">Wyloguj się</button>
            </form>
        <?php else: ?>
            <p>
                <a herf="?strona=logownaie">Logownaie</a>
                <a herf="?strona=rejestracja">Rejestracja</a>
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
                <?php endif: ?>

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
                </bottom>
            </form>
        <?php else: ?>
            <h2>Panel: <?= h($strona) ?></h2>
            <p>Witaj, <?= h($user['name'] . ' ' . $user['surname']) ?>!</p>
            <p>Panel jest chroniony. Funkcje zarządzania i rezerwacji zostaną dodane w kolejnych etapach.</p>
        <?php endif; ?>
    </main>
</body>
</html>