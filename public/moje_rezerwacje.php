<?php
require __DIR__ . '/start.php';
require __DIR__ . '/rezerwacje_funkcje.php';
wymagaj_roli($user ?? null, ['client']);

$blad = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['akcja'] ?? '') === 'anuluj') {
    if (!token_ok()) {
        $blad = 'Sesja formularza wygasła. Spróbuj ponownie.';
    } else {
        // user_id w WHERE gwarantuje, że klient anuluje tylko własną, przyszłą rezerwację
        $s = $pdo->prepare(
            "UPDATE reservations SET status = 'anulowana'
             WHERE id = ? AND user_id = ? AND status IN ('oczekujaca', 'potwierdzona') AND rental_start > ?"
        );
        $s->execute([(int) ($_POST['id'] ?? 0), $user['id'], date('Y-m-d H:i:s')]);
        if ($s->rowCount() === 1) {
            $_SESSION['ok'] = 'Rezerwacja została anulowana.';
            header('Location: moje_rezerwacje.php');
            exit;
        }
        $blad = 'Tej rezerwacji nie można anulować.';
    }
}

$s = $pdo->prepare(
    'SELECT r.id, r.rental_start, r.rental_end, r.status, q.name AS sprzet, q.daily_price,
            u.name AS p_imie, u.surname AS p_nazwisko
     FROM reservations r
     JOIN equipment q ON q.id = r.equipment_id
     JOIN employees e ON e.id = r.employee_id
     JOIN users u ON u.id = e.user_id
     WHERE r.user_id = ?
     ORDER BY r.rental_start'
);
$s->execute([$user['id']]);
$wszystkie = $s->fetchAll(PDO::FETCH_ASSOC);

$teraz = date('Y-m-d H:i:s');
$przyszle = $koniec = [];
foreach ($wszystkie as $r) {
    if (in_array($r['status'], STATUSY_AKTYWNE, true) && $r['rental_end'] >= $teraz) {
        $przyszle[] = $r;
    } else {
        $koniec[] = $r;
    }
}

function tabela_klienta(array $wiersze, bool $anuluj, string $teraz): void
{
    if (!$wiersze) {
        echo '<p>Brak rezerwacji.</p>';
        return;
    }
    echo '<div class="tabela"><table><tr><th>Sprzęt</th><th>Pracownik</th><th>Odbiór</th><th>Zwrot</th><th>Cena</th><th>Status</th><th></th></tr>';
    foreach ($wiersze as $r) {
        $cena = liczba_dni($r['rental_start'], $r['rental_end']) * (float) $r['daily_price'];
        echo '<tr><td>' . h($r['sprzet']) . '</td><td>' . h($r['p_imie'] . ' ' . $r['p_nazwisko']) . '</td><td>'
            . h(data_pl($r['rental_start'])) . '</td><td>' . h(data_pl($r['rental_end'])) . '</td><td>' . zl($cena)
            . '</td><td>' . h($r['status']) . '</td><td>';
        if ($anuluj && $r['rental_start'] > $teraz) {
            echo '<form method="post" onsubmit="return confirm(\'Anulować rezerwację?\');">'
                . '<input type="hidden" name="token" value="' . h($_SESSION['token']) . '">'
                . '<input type="hidden" name="akcja" value="anuluj"><input type="hidden" name="id" value="' . (int) $r['id'] . '">'
                . '<button type="submit" class="maly usun">Anuluj</button></form>';
        }
        echo '</td></tr>';
    }
    echo '</table></div>';
}

naglowek('Moje rezerwacje', $user);
komunikat_flash();
if ($blad !== '') {
    echo '<p class="blad">' . h($blad) . '</p>';
}
echo '<h2>Aktualne i przyszłe</h2>';
tabela_klienta($przyszle, true, $teraz);
echo '<h2>Zakończone i anulowane</h2>';
tabela_klienta($koniec, false, $teraz);
stopka();
