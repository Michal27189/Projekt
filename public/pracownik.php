<?php
require __DIR__ . '/start.php';
require __DIR__ . '/rezerwacje_funkcje.php';
wymagaj_roli($user ?? null, ['employee']);

$s = $pdo->prepare('SELECT id FROM employees WHERE user_id = ? AND active = 1');
$s->execute([$user['id']]);
$pracownik_id = (int) $s->fetchColumn();
if ($pracownik_id === 0) {
    http_response_code(403);
    exit('Brak aktywnego profilu pracownika.');
}

$blad = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['akcja'] ?? '') === 'rezerwacja_status') {
    $id = (int) ($_POST['id'] ?? 0);
    $nowy = (string) ($_POST['status'] ?? '');
    if (!token_ok()) {
        $blad = 'Sesja formularza wygasła. Spróbuj ponownie.';
    } elseif (!in_array($nowy, STATUSY_WSZYSTKIE, true)) {
        $blad = 'Niepoprawny status.';
    } else {
        // Tylko rezerwacja przypisana do tego pracownika
        $s = $pdo->prepare('SELECT equipment_id, rental_start, rental_end, status FROM reservations WHERE id = ? AND employee_id = ?');
        $s->execute([$id, $pracownik_id]);
        $r = $s->fetch(PDO::FETCH_ASSOC);
        if (!$r) {
            $blad = 'Nie masz dostępu do tej rezerwacji.';
        } elseif (in_array($nowy, STATUSY_AKTYWNE, true) && !in_array($r['status'], STATUSY_AKTYWNE, true)
            && kolizja_sprzetu($pdo, (int) $r['equipment_id'], new DateTime($r['rental_start']), new DateTime($r['rental_end']), $id)) {
            $blad = 'Nie można przywrócić rezerwacji: termin jest już zajęty przez inną rezerwację.';
        } else {
            $s = $pdo->prepare('UPDATE reservations SET status = ? WHERE id = ? AND employee_id = ?');
            $s->execute([$nowy, $id, $pracownik_id]);
            $_SESSION['ok'] = 'Zmieniono status rezerwacji.';
            $cel = (($_POST['wroc'] ?? '') === 'szczegoly') ? 'pracownik.php?id=' . $id : 'pracownik.php';
            header('Location: ' . $cel);
            exit;
        }
    }
}

$pola = 'r.id, r.rental_start, r.rental_end, r.status, r.comment, q.name AS sprzet, q.daily_price,
         k.name AS k_imie, k.surname AS k_nazwisko, k.phone AS k_telefon, k.email AS k_email';
$z = 'FROM reservations r JOIN equipment q ON q.id = r.equipment_id JOIN users k ON k.id = r.user_id';

function formularz_statusu(array $r, string $wroc = ''): void
{
    echo '<form method="post"><input type="hidden" name="token" value="' . h($_SESSION['token']) . '">'
        . '<input type="hidden" name="id" value="' . (int) $r['id'] . '"><input type="hidden" name="wroc" value="' . h($wroc) . '"><select name="status">';
    foreach (STATUSY_WSZYSTKIE as $st) {
        echo '<option value="' . $st . '"' . ($st === $r['status'] ? ' selected' : '') . '>' . $st . '</option>';
    }
    echo '</select> <button type="submit" name="akcja" value="rezerwacja_status" class="maly">Zmień</button></form>';
}

// Szczegóły jednej rezerwacji
if (isset($_GET['id'])) {
    $s = $pdo->prepare("SELECT $pola $z WHERE r.id = ? AND r.employee_id = ?");
    $s->execute([(int) $_GET['id'], $pracownik_id]);
    $r = $s->fetch(PDO::FETCH_ASSOC);
    naglowek('Szczegóły rezerwacji', $user);
    komunikat_flash();
    if ($blad !== '') {
        echo '<p class="blad">' . h($blad) . '</p>';
    }
    if (!$r) {
        http_response_code(403);
        echo '<p class="blad">Nie masz dostępu do tej rezerwacji.</p>';
    } else {
        $dni = liczba_dni($r['rental_start'], $r['rental_end']);
        echo '<div class="tabela"><table>';
        echo '<tr><th>Sprzęt</th><td>' . h($r['sprzet']) . '</td></tr>';
        echo '<tr><th>Klient</th><td>' . h($r['k_imie'] . ' ' . $r['k_nazwisko']) . '</td></tr>';
        echo '<tr><th>Telefon</th><td>' . h($r['k_telefon'] ?? '-') . '</td></tr>';
        echo '<tr><th>E-mail</th><td>' . h($r['k_email']) . '</td></tr>';
        echo '<tr><th>Odbiór</th><td>' . h(data_pl($r['rental_start'])) . '</td></tr>';
        echo '<tr><th>Zwrot</th><td>' . h(data_pl($r['rental_end'])) . ' (' . $dni . ' dób)</td></tr>';
        echo '<tr><th>Cena</th><td>' . zl($dni * (float) $r['daily_price']) . '</td></tr>';
        echo '<tr><th>Uwagi</th><td>' . h($r['comment'] ?? '-') . '</td></tr>';
        echo '<tr><th>Status</th><td>' . h($r['status']) . '</td></tr></table></div>';
        formularz_statusu($r, 'szczegoly');
    }
    echo '<p><a href="pracownik.php">Wróć do listy</a></p>';
    stopka();
    exit;
}

$dzis = date('Y-m-d');
$aktywne = "r.status IN ('oczekujaca', 'potwierdzona')";
$grupy = [
    'Dziś i w trakcie' => ["$aktywne AND DATE(r.rental_start) <= ? AND DATE(r.rental_end) >= ?", [$dzis, $dzis], 'r.rental_start'],
    'Przyszłe' => ["$aktywne AND DATE(r.rental_start) > ?", [$dzis], 'r.rental_start'],
    'Historia' => ["(r.status NOT IN ('oczekujaca', 'potwierdzona') OR DATE(r.rental_end) < ?)", [$dzis], 'r.rental_start DESC'],
];

naglowek('Moje wizyty', $user);
komunikat_flash();
if ($blad !== '') {
    echo '<p class="blad">' . h($blad) . '</p>';
}
foreach ($grupy as $tytul => [$warunek, $param, $sort]) {
    $s = $pdo->prepare("SELECT $pola $z WHERE r.employee_id = ? AND $warunek ORDER BY $sort LIMIT 50");
    $s->execute(array_merge([$pracownik_id], $param));
    $wiersze = $s->fetchAll(PDO::FETCH_ASSOC);
    echo '<h2>' . h($tytul) . '</h2>';
    if (!$wiersze) {
        echo '<p>Brak rezerwacji.</p>';
        continue;
    }
    echo '<div class="tabela"><table><tr><th>Klient</th><th>Sprzęt</th><th>Odbiór</th><th>Zwrot</th><th>Status</th><th>Zmiana statusu</th><th></th></tr>';
    foreach ($wiersze as $r) {
        echo '<tr><td>' . h($r['k_imie'] . ' ' . $r['k_nazwisko']) . '</td><td>' . h($r['sprzet']) . '</td><td>'
            . h(data_pl($r['rental_start'])) . '</td><td>' . h(data_pl($r['rental_end'])) . '</td><td>' . h($r['status']) . '</td><td>';
        formularz_statusu($r);
        echo '</td><td><a href="pracownik.php?id=' . (int) $r['id'] . '">Szczegóły</a></td></tr>';
    }
    echo '</table></div>';
}
stopka();
