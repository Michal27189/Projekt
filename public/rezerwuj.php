<?php
require __DIR__ . '/start.php';
require __DIR__ . '/rezerwacje_funkcje.php';
wymagaj_roli($user ?? null, ['client']);

$sprzet_id = (int) ($_GET['sprzet'] ?? $_POST['sprzet'] ?? 0);
$s = $pdo->prepare(
    'SELECT q.*, c.name AS kategoria FROM equipment q
     JOIN equipment_categories c ON c.id = q.category_id
     WHERE q.id = ? AND q.active = 1'
);
$s->execute([$sprzet_id]);
$sprzet = $s->fetch(PDO::FETCH_ASSOC);

naglowek('Rezerwacja sprzętu', $user);
if (!$sprzet) {
    echo '<p class="blad">Nie znaleziono takiego sprzętu.</p>';
    stopka();
    exit;
}

// Pracownicy obsługujący ten sprzęt (krok 1)
$s = $pdo->prepare(
    'SELECT e.id, u.name, u.surname, e.description FROM employees e
     JOIN users u ON u.id = e.user_id
     JOIN employee_equipment ee ON ee.employee_id = e.id
     WHERE ee.equipment_id = ? AND e.active = 1 AND u.active = 1
     ORDER BY u.surname'
);
$s->execute([$sprzet_id]);
$pracownicy = $s->fetchAll(PDO::FETCH_ASSOC);

$pracownik = null;
$pracownik_id = (int) ($_GET['pracownik'] ?? $_POST['pracownik'] ?? 0);
foreach ($pracownicy as $p) {
    if ((int) $p['id'] === $pracownik_id) {
        $pracownik = $p;
    }
}

// Data (krok 2)
$dzien = null;
$data_tekst = (string) ($_GET['data'] ?? $_POST['data'] ?? '');
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_tekst)) {
    $d = DateTime::createFromFormat('Y-m-d', $data_tekst);
    if ($d && $d->format('Y-m-d') === $data_tekst) {
        $dzien = $d;
    }
}

$blad = '';
$krok = $_SERVER['REQUEST_METHOD'] === 'POST' ? (string) ($_POST['krok'] ?? '') : '';
$godzina = (string) ($_POST['godzina'] ?? '');
$dni = (int) ($_POST['dni'] ?? 1);
$start = $koniec = null;

if ($krok !== '') {
    if (!token_ok()) {
        $blad = 'Sesja formularza wygasła. Spróbuj ponownie.';
        $krok = '';
    } elseif (!$pracownik || !$dzien || !preg_match('/^\d{2}:\d{2}$/', $godzina) || $dni < 1 || $dni > 14) {
        $blad = 'Wybierz poprawnie pracownika, datę, godzinę i liczbę dni (1-14).';
        $krok = '';
    } else {
        $start = DateTime::createFromFormat('Y-m-d H:i', $dzien->format('Y-m-d') . ' ' . $godzina);
        if (!$start) {
            $blad = 'Niepoprawna godzina.';
            $krok = '';
        } else {
            $koniec = clone $start;
            $koniec->modify('+' . $dni . ' days');
        }
    }
}

// Zapis: sprawdzenie konfliktu w transakcji, tuż przed INSERT
if ($krok === 'zapisz') {
    try {
        $pdo->beginTransaction();
        $blokada = $pdo->prepare('SELECT id FROM equipment WHERE id = ? FOR UPDATE');
        $blokada->execute([$sprzet_id]);
        $blokada->fetchAll();

        $blad = sprawdz_termin($pdo, $sprzet_id, (int) $pracownik['id'], $start, $koniec) ?? '';
        if ($blad === '') {
            $zapis = $pdo->prepare(
                "INSERT INTO reservations (user_id, employee_id, equipment_id, rental_start, rental_end, status)
                 VALUES (?, ?, ?, ?, ?, 'oczekujaca')"
            );
            $zapis->execute([$user['id'], $pracownik['id'], $sprzet_id, $start->format('Y-m-d H:i:s'), $koniec->format('Y-m-d H:i:s')]);
            $pdo->commit();
            $_SESSION['ok'] = 'Rezerwacja została zapisana i czeka na potwierdzenie.';
            header('Location: moje_rezerwacje.php');
            exit;
        }
        $pdo->rollBack();
        $krok = '';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log($e->getMessage());
        $blad = 'Nie udało się zapisać rezerwacji. Spróbuj ponownie.';
        $krok = '';
    }
}

// Podsumowanie (krok 4): walidacja przed potwierdzeniem
if ($krok === 'podsumowanie') {
    $blad = sprawdz_termin($pdo, $sprzet_id, (int) $pracownik['id'], $start, $koniec) ?? '';
    if ($blad !== '') {
        $krok = '';
    }
}

echo '<h2>' . h($sprzet['name']) . '</h2>';
echo '<p>' . h($sprzet['kategoria']) . ', ' . zl((float) $sprzet['daily_price']) . ' za dobę, kaucja ' . zl((float) $sprzet['deposit']) . '</p>';
if ($blad !== '') {
    echo '<p class="blad">' . h($blad) . '</p>';
}

if ($krok === 'podsumowanie') {
    $cena = $dni * (float) $sprzet['daily_price'];
    echo '<h2>Potwierdzenie</h2><div class="tabela"><table>';
    echo '<tr><th>Sprzęt</th><td>' . h($sprzet['name']) . '</td></tr>';
    echo '<tr><th>Pracownik</th><td>' . h($pracownik['name'] . ' ' . $pracownik['surname']) . '</td></tr>';
    echo '<tr><th>Odbiór</th><td>' . h($start->format('d.m.Y H:i')) . '</td></tr>';
    echo '<tr><th>Zwrot</th><td>' . h($koniec->format('d.m.Y H:i')) . ' (' . $dni . ' dób)</td></tr>';
    echo '<tr><th>Cena wynajmu</th><td>' . zl($cena) . '</td></tr>';
    echo '<tr><th>Kaucja</th><td>' . zl((float) $sprzet['deposit']) . '</td></tr></table></div>';
    echo '<form method="post"><input type="hidden" name="token" value="' . h($_SESSION['token']) . '">';
    echo '<input type="hidden" name="krok" value="zapisz">';
    echo '<input type="hidden" name="sprzet" value="' . $sprzet_id . '">';
    echo '<input type="hidden" name="pracownik" value="' . (int) $pracownik['id'] . '">';
    echo '<input type="hidden" name="data" value="' . h($dzien->format('Y-m-d')) . '">';
    echo '<input type="hidden" name="godzina" value="' . h($godzina) . '">';
    echo '<input type="hidden" name="dni" value="' . $dni . '">';
    echo '<button type="submit">Potwierdzam rezerwację</button></form>';
    echo '<p><a href="rezerwuj.php?sprzet=' . $sprzet_id . '&amp;pracownik=' . (int) $pracownik['id'] . '&amp;data=' . h($dzien->format('Y-m-d')) . '">Zmień termin</a></p>';
    stopka();
    exit;
}

if (!$pracownicy) {
    echo '<p class="blad">Żaden pracownik nie obsługuje obecnie tego sprzętu.</p>';
} elseif (!$pracownik) {
    echo '<h2>1. Wybierz pracownika</h2><ul>';
    foreach ($pracownicy as $p) {
        echo '<li><a href="rezerwuj.php?sprzet=' . $sprzet_id . '&amp;pracownik=' . (int) $p['id'] . '">'
            . h($p['name'] . ' ' . $p['surname']) . '</a> ' . h($p['description'] ?? '') . '</li>';
    }
    echo '</ul>';
} else {
    echo '<p>Pracownik: <strong>' . h($pracownik['name'] . ' ' . $pracownik['surname']) . '</strong> (<a href="rezerwuj.php?sprzet=' . $sprzet_id . '">zmień</a>)</p>';

    $s = $pdo->prepare('SELECT day_of_week, start_time, end_time FROM employee_availabilty WHERE employee_id = ? ORDER BY day_of_week, start_time');
    $s->execute([$pracownik['id']]);
    echo '<p>Godziny pracy: ';
    $opisy = [];
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $g) {
        $opisy[] = DNI_TYGODNIA[(int) $g['day_of_week']] . ' ' . substr($g['start_time'], 0, 5) . '-' . substr($g['end_time'], 0, 5);
    }
    echo h(implode(', ', $opisy)) . '</p>';

    echo '<h2>2. Wybierz datę odbioru</h2>';
    echo '<form method="get" class="filtry"><input type="hidden" name="sprzet" value="' . $sprzet_id . '">';
    echo '<input type="hidden" name="pracownik" value="' . (int) $pracownik['id'] . '">';
    echo '<label>Data odbioru <input type="date" name="data" required min="' . date('Y-m-d') . '" value="' . h($dzien ? $dzien->format('Y-m-d') : '') . '"></label>';
    echo '<button type="submit">Pokaż godziny</button></form>';

    if ($dzien) {
        $wolne = $dzien->format('Y-m-d') < date('Y-m-d') ? [] : wolne_odbiory($pdo, $sprzet_id, (int) $pracownik['id'], $dzien);
        echo '<h2>3. Wybierz godzinę i czas wypożyczenia</h2>';
        if (!$wolne) {
            echo '<p class="blad">Brak wolnych godzin odbioru w tym dniu. Wybierz inną datę.</p>';
        } else {
            echo '<form method="post"><input type="hidden" name="token" value="' . h($_SESSION['token']) . '">';
            echo '<input type="hidden" name="krok" value="podsumowanie">';
            echo '<input type="hidden" name="sprzet" value="' . $sprzet_id . '">';
            echo '<input type="hidden" name="pracownik" value="' . (int) $pracownik['id'] . '">';
            echo '<input type="hidden" name="data" value="' . h($dzien->format('Y-m-d')) . '">';
            echo '<label>Godzina odbioru <select name="godzina">';
            foreach ($wolne as $g) {
                echo '<option value="' . h($g) . '"' . ($g === $godzina ? ' selected' : '') . '>' . h($g) . '</option>';
            }
            echo '</select></label>';
            echo '<label>Liczba dób (1-14) <input type="number" name="dni" min="1" max="14" value="' . max(1, min(14, $dni)) . '" required></label>';
            echo '<button type="submit">Dalej</button></form>';
        }

        $s = $pdo->prepare(
            "SELECT rental_start, rental_end FROM reservations
             WHERE equipment_id = ? AND status IN ('oczekujaca', 'potwierdzona') AND rental_end > ?
             ORDER BY rental_start"
        );
        $s->execute([$sprzet_id, date('Y-m-d H:i:s')]);
        $zajete = $s->fetchAll(PDO::FETCH_ASSOC);
        if ($zajete) {
            echo '<p>Zajęte terminy tego sprzętu:</p><ul>';
            foreach ($zajete as $z) {
                echo '<li>' . h(data_pl($z['rental_start'])) . ' - ' . h(data_pl($z['rental_end'])) . '</li>';
            }
            echo '</ul>';
        }
    }
}
stopka();
