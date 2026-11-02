<?php
require __DIR__ . '/start.php';

// 1. Ochrona: wejść może tylko zalogowany administrator.
if (!$user) {
    header('Location: index.php?strona=logowanie');
    exit;
}
if ($user['role'] !== 'admin') {
    http_response_code(403);
    exit('Brak uprawnień.');
}

// 2. Ustawienia i krótkie funkcje pomocnicze.
const NA_STRONE = 10;

$sekcje = [
    'dashboard'   => 'Dashboard',
    'kategorie'   => 'Kategorie',
    'sprzet'      => 'Sprzęt',
    'pracownicy'  => 'Pracownicy',
    'uzytkownicy' => 'Użytkownicy',
    'rezerwacje'  => 'Rezerwacje',
];
$dni = [1 => 'Poniedziałek', 2 => 'Wtorek', 3 => 'Środa', 4 => 'Czwartek', 5 => 'Piątek', 6 => 'Sobota', 0 => 'Niedziela'];
$statusy = ['oczekujaca' => 'oczekująca', 'potwierdzona' => 'potwierdzona', 'zrealizowana' => 'zrealizowana', 'anulowana' => 'anulowana'];
$role = ['client' => 'klient', 'employee' => 'pracownik', 'admin' => 'administrator'];

function liczba_z($tekst)
{
    // Tylko cyfry, najwyżej 9 znaków. Wszystko inne daje 0.
    return ctype_digit($tekst) && strlen($tekst) <= 9 ? (int) $tekst : 0;
}

function z_adresu($nazwa)
{
    return isset($_GET[$nazwa]) && is_string($_GET[$nazwa]) ? trim($_GET[$nazwa]) : '';
}

function wroc($sekcja, $komunikat)
{
    $_SESSION['sukces'] = $komunikat;
    header('Location: admin.php?sekcja=' . urlencode($sekcja));
    exit;
}

function ile($pdo, $sql, $parametry = [])
{
    $q = $pdo->prepare($sql);
    $q->execute($parametry);
    return (int) $q->fetchColumn();
}

function cena($tekst)
{
    // Zwraca liczbę z kropką albo null, gdy zapis jest błędny.
    $tekst = str_replace(',', '.', trim($tekst));
    return preg_match('/^\d{1,6}(\.\d{1,2})?$/', $tekst) ? $tekst : null;
}

function godzina($tekst)
{
    return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $tekst) ? $tekst . ':00' : null;
}

function wart($stare, $klucz, $domyslna = '')
{
    // Wartość pola: najpierw to, co wpisano w formularzu, potem dane z bazy.
    return h($stare[$klucz] ?? $domyslna);
}

function waliduj_osobe($imie, $nazwisko, $telefon)
{
    $bledy = [];
    if ($imie === '' || mb_strlen($imie) > 50) {
        $bledy[] = 'Imię: od 1 do 50 znaków.';
    }
    if ($nazwisko === '' || mb_strlen($nazwisko) > 50) {
        $bledy[] = 'Nazwisko: od 1 do 50 znaków.';
    }
    if (!preg_match('/^[0-9+ ()-]{6,20}$/', $telefon)) {
        $bledy[] = 'Telefon: 6–20 znaków (cyfry, spacje, +, -, nawiasy).';
    }
    return $bledy;
}

function linki_stron($razem, $obecna, $parametry)
{
    $stron = (int) ceil($razem / NA_STRONE);
    if ($stron <= 1) {
        return;
    }
    echo '<p class="strony">Strona: ';
    for ($i = 1; $i <= $stron; $i++) {
        if ($i === $obecna) {
            echo '<strong>' . $i . '</strong> ';
        } else {
            echo '<a href="?' . h(http_build_query($parametry + ['p' => $i])) . '">' . $i . '</a> ';
        }
    }
    echo '</p>';
}

$sekcja = z_adresu('sekcja');
if (!isset($sekcje[$sekcja])) {
    $sekcja = 'dashboard';
}
$edytuj = liczba_z(z_adresu('edytuj'));
$obecna = max(1, min(100000, liczba_z(z_adresu('p'))));
$przesuniecie = ($obecna - 1) * NA_STRONE;
$bledy = [];
$stare = [];

// 3. Obsługa formularzy (każda zmiana danych idzie przez POST z tokenem).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['token'], pole('token'))) {
        http_response_code(403);
        exit('Nieprawidłowy formularz. Odśwież stronę.');
    }
    foreach ($_POST as $klucz => $wartosc) {
        if (is_string($wartosc)) {
            $stare[$klucz] = $wartosc;
        }
    }
    $akcja = pole('akcja');
    $id = liczba_z(pole('id'));

    if ($akcja === 'kategoria_zapisz') {
        $nazwa = trim(pole('nazwa'));
        $opis = trim(pole('opis'));
        if ($nazwa === '' || mb_strlen($nazwa) > 100) {
            $bledy[] = 'Nazwa kategorii: od 1 do 100 znaków.';
        }
        if (mb_strlen($opis) > 1000) {
            $bledy[] = 'Opis: maksymalnie 1000 znaków.';
        }
        if (!$bledy) {
            try {
                if ($id > 0) {
                    $q = $pdo->prepare('UPDATE equipment_categories SET name = ?, description = ? WHERE id = ?');
                    $q->execute([$nazwa, $opis === '' ? null : $opis, $id]);
                } else {
                    $q = $pdo->prepare('INSERT INTO equipment_categories (name, description) VALUES (?, ?)');
                    $q->execute([$nazwa, $opis === '' ? null : $opis]);
                }
                wroc('kategorie', 'Zapisano kategorię.');
            } catch (PDOException $blad) {
                if ($blad->getCode() === '23000') {
                    $bledy[] = 'Kategoria o takiej nazwie już istnieje.';
                } else {
                    throw $blad;
                }
            }
        }
    } elseif ($akcja === 'kategoria_usun') {
        if (ile($pdo, 'SELECT COUNT(*) FROM equipment WHERE category_id = ?', [$id]) > 0) {
            $bledy[] = 'Nie można usunąć kategorii, która ma sprzęt. Najpierw przenieś lub usuń sprzęt.';
        } else {
            $q = $pdo->prepare('DELETE FROM equipment_categories WHERE id = ?');
            $q->execute([$id]);
            wroc('kategorie', 'Usunięto kategorię.');
        }
    } elseif ($akcja === 'sprzet_zapisz') {
        $kategoria = liczba_z(pole('kategoria'));
        $nazwa = trim(pole('nazwa'));
        $opis = trim(pole('opis'));
        $cena_doba = cena(pole('cena'));
        $kaucja = cena(pole('kaucja'));
        $aktywny = pole('aktywny') === '1' ? 1 : 0;
        if (ile($pdo, 'SELECT COUNT(*) FROM equipment_categories WHERE id = ?', [$kategoria]) === 0) {
            $bledy[] = 'Wybierz istniejącą kategorię.';
        }
        if ($nazwa === '' || mb_strlen($nazwa) > 150) {
            $bledy[] = 'Nazwa sprzętu: od 1 do 150 znaków.';
        }
        if (mb_strlen($opis) > 1000) {
            $bledy[] = 'Opis: maksymalnie 1000 znaków.';
        }
        if ($cena_doba === null) {
            $bledy[] = 'Cena za dobę: liczba od 0 do 999999,99 (najwyżej 2 miejsca po przecinku).';
        }
        if ($kaucja === null) {
            $bledy[] = 'Kaucja: liczba od 0 do 999999,99 (najwyżej 2 miejsca po przecinku).';
        }
        if (!$bledy) {
            if ($id > 0) {
                $q = $pdo->prepare('UPDATE equipment SET category_id = ?, name = ?, description = ?, daily_price = ?, deposit = ?, active = ? WHERE id = ?');
                $q->execute([$kategoria, $nazwa, $opis === '' ? null : $opis, $cena_doba, $kaucja, $aktywny, $id]);
            } else {
                $q = $pdo->prepare('INSERT INTO equipment (category_id, name, description, daily_price, deposit, active) VALUES (?, ?, ?, ?, ?, ?)');
                $q->execute([$kategoria, $nazwa, $opis === '' ? null : $opis, $cena_doba, $kaucja, $aktywny]);
            }
            wroc('sprzet', 'Zapisano sprzęt.');
        }
    } elseif ($akcja === 'sprzet_aktywnosc') {
        $q = $pdo->prepare('UPDATE equipment SET active = 1 - active WHERE id = ?');
        $q->execute([$id]);
        wroc('sprzet', 'Zmieniono aktywność sprzętu.');
    } elseif ($akcja === 'sprzet_usun') {
        if (ile($pdo, 'SELECT COUNT(*) FROM reservations WHERE equipment_id = ?', [$id]) > 0) {
            $bledy[] = 'Ten sprzęt ma rezerwacje, więc nie można go usunąć. Dezaktywuj go.';
        } else {
            $q = $pdo->prepare('DELETE FROM equipment WHERE id = ?');
            $q->execute([$id]);
            wroc('sprzet', 'Usunięto sprzęt.');
        }
    } elseif ($akcja === 'pracownik_dodaj') {
        $imie = trim(pole('imie'));
        $nazwisko = trim(pole('nazwisko'));
        $telefon = trim(pole('telefon'));
        $email = trim(pole('email'));
        $haslo = pole('haslo');
        $opis = trim(pole('opis'));
        $bledy = waliduj_osobe($imie, $nazwisko, $telefon);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
            $bledy[] = 'Wpisz poprawny e-mail, do 100 znaków.';
        }
        if (strlen($haslo) < 8 || strlen($haslo) > 72) {
            $bledy[] = 'Hasło: 8–72 bajty.';
        }
        if (mb_strlen($opis) > 1000) {
            $bledy[] = 'Opis: maksymalnie 1000 znaków.';
        }
        if (!$bledy && ile($pdo, 'SELECT COUNT(*) FROM users WHERE email = ?', [$email]) > 0) {
            $bledy[] = 'E-mail jest już zajęty.';
        }
        if (!$bledy) {
            $pdo->beginTransaction();
            try {
                $q = $pdo->prepare("INSERT INTO users (name, surname, phone, email, password, role, active) VALUES (?, ?, ?, ?, ?, 'employee', 1)");
                $q->execute([$imie, $nazwisko, $telefon, $email, password_hash($haslo, PASSWORD_BCRYPT)]);
                $nowy_id = $pdo->lastInsertId();
                $q = $pdo->prepare('INSERT INTO employees (user_id, description, active) VALUES (?, ?, 1)');
                $q->execute([$nowy_id, $opis === '' ? null : $opis]);
                $pdo->commit();
            } catch (PDOException $blad) {
                $pdo->rollBack();
                if ($blad->getCode() === '23000') {
                    $bledy[] = 'Nie można dodać pracownika. E-mail może być zajęty.';
                } else {
                    throw $blad;
                }
            }
            if (!$bledy) {
                wroc('pracownicy', 'Dodano pracownika.');
            }
        }
    } elseif ($akcja === 'pracownik_zapisz') {
        $opis = trim(pole('opis'));
        $aktywny = pole('aktywny') === '1' ? 1 : 0;
        $wybrane = isset($_POST['sprzet']) && is_array($_POST['sprzet']) ? $_POST['sprzet'] : [];
        if (ile($pdo, 'SELECT COUNT(*) FROM employees WHERE id = ?', [$id]) === 0) {
            $bledy[] = 'Nie ma takiego pracownika.';
        }
        if (mb_strlen($opis) > 1000) {
            $bledy[] = 'Opis: maksymalnie 1000 znaków.';
        }
        $godziny = [];
        foreach ($dni as $nr => $nazwa_dnia) {
            if (pole('dzien' . $nr) !== '1') {
                continue;
            }
            $od = godzina(pole('od' . $nr));
            $do = godzina(pole('do' . $nr));
            if ($od === null || $do === null || $do <= $od) {
                $bledy[] = $nazwa_dnia . ': podaj godziny pracy, koniec musi być po początku.';
                continue;
            }
            $godziny[$nr] = [$od, $do];
        }
        if (!$bledy) {
            $pdo->beginTransaction();
            $q = $pdo->prepare('UPDATE employees SET description = ?, active = ? WHERE id = ?');
            $q->execute([$opis === '' ? null : $opis, $aktywny, $id]);

            $q = $pdo->prepare('DELETE FROM employee_equipment WHERE employee_id = ?');
            $q->execute([$id]);
            $wszystkie = $pdo->query('SELECT id FROM equipment')->fetchAll(PDO::FETCH_COLUMN);
            $q = $pdo->prepare('INSERT INTO employee_equipment (employee_id, equipment_id) VALUES (?, ?)');
            foreach ($wszystkie as $sprzet_id) {
                if (in_array((string) $sprzet_id, $wybrane, true)) {
                    $q->execute([$id, $sprzet_id]);
                }
            }

            $q = $pdo->prepare('DELETE FROM employee_availabilty WHERE employee_id = ?');
            $q->execute([$id]);
            $q = $pdo->prepare('INSERT INTO employee_availabilty (employee_id, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?)');
            foreach ($godziny as $nr => $para) {
                $q->execute([$id, $nr, $para[0], $para[1]]);
            }
            $pdo->commit();
            wroc('pracownicy', 'Zapisano dane pracownika.');
        }
    } elseif ($akcja === 'pracownik_aktywnosc') {
        $q = $pdo->prepare('UPDATE employees SET active = 1 - active WHERE id = ?');
        $q->execute([$id]);
        wroc('pracownicy', 'Zmieniono aktywność pracownika.');
    } elseif ($akcja === 'uzytkownik_aktywnosc') {
        if ($id === (int) $user['id']) {
            $bledy[] = 'Nie możesz dezaktywować własnego konta.';
        } else {
            $q = $pdo->prepare('UPDATE users SET active = 1 - active WHERE id = ?');
            $q->execute([$id]);
            wroc('uzytkownicy', 'Zmieniono aktywność konta.');
        }
    } elseif ($akcja === 'rezerwacja_status') {
        $nowy = pole('status');
        if (!isset($statusy[$nowy])) {
            $bledy[] = 'Nieprawidłowy status.';
        } else {
            $q = $pdo->prepare('UPDATE reservations SET status = ? WHERE id = ?');
            $q->execute([$nowy, $id]);
            wroc('rezerwacje', 'Zmieniono status rezerwacji.');
        }
    }
}

// 4. Dane do wyświetlenia (zależne od wybranej sekcji).
$szukaj = z_adresu('q');
if ($sekcja === 'dashboard') {
    $dzis = date('Y-m-d');
    $liczniki = [
        'Klienci' => ile($pdo, "SELECT COUNT(*) FROM users WHERE role = 'client'"),
        'Pracownicy' => ile($pdo, 'SELECT COUNT(*) FROM employees'),
        'Sprzęt' => ile($pdo, 'SELECT COUNT(*) FROM equipment'),
        'Wszystkie rezerwacje' => ile($pdo, 'SELECT COUNT(*) FROM reservations'),
        'Rezerwacje na dziś' => ile($pdo, "SELECT COUNT(*) FROM reservations WHERE DATE(rental_start) <= ? AND DATE(rental_end) >= ? AND status <> 'anulowana'", [$dzis, $dzis]),
    ];
    $wg_statusu = $pdo->query('SELECT status, COUNT(*) AS ile FROM reservations GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
} elseif ($sekcja === 'kategorie') {
    $wiersz = null;
    if ($edytuj) {
        $q = $pdo->prepare('SELECT * FROM equipment_categories WHERE id = ?');
        $q->execute([$edytuj]);
        $wiersz = $q->fetch() ?: null;
    }
    $kategorie = $pdo->query('SELECT c.id, c.name, c.description, (SELECT COUNT(*) FROM equipment e WHERE e.category_id = c.id) AS ile_sprzetu FROM equipment_categories c ORDER BY c.name')->fetchAll();
} elseif ($sekcja === 'sprzet') {
    $wiersz = null;
    if ($edytuj) {
        $q = $pdo->prepare('SELECT * FROM equipment WHERE id = ?');
        $q->execute([$edytuj]);
        $wiersz = $q->fetch() ?: null;
    }
    $lista_kategorii = $pdo->query('SELECT id, name FROM equipment_categories ORDER BY name')->fetchAll();
    $warunek = $szukaj !== '' ? 'WHERE e.name LIKE ?' : '';
    $parametry_sql = $szukaj !== '' ? ['%' . $szukaj . '%'] : [];
    $razem = ile($pdo, "SELECT COUNT(*) FROM equipment e $warunek", $parametry_sql);
    $q = $pdo->prepare("SELECT e.id, e.name, e.daily_price, e.deposit, e.active, c.name AS kategoria FROM equipment e JOIN equipment_categories c ON c.id = e.category_id $warunek ORDER BY e.id LIMIT " . NA_STRONE . ' OFFSET ' . $przesuniecie);
    $q->execute($parametry_sql);
    $sprzet = $q->fetchAll();
} elseif ($sekcja === 'pracownicy') {
    $pracownik = null;
    if ($edytuj) {
        $q = $pdo->prepare('SELECT p.id, p.description, p.active, u.name, u.surname, u.email FROM employees p JOIN users u ON u.id = p.user_id WHERE p.id = ?');
        $q->execute([$edytuj]);
        $pracownik = $q->fetch() ?: null;
    }
    if ($pracownik) {
        $caly_sprzet = $pdo->query('SELECT id, name FROM equipment ORDER BY name')->fetchAll();
        $q = $pdo->prepare('SELECT equipment_id FROM employee_equipment WHERE employee_id = ?');
        $q->execute([$edytuj]);
        $przypisane = $q->fetchAll(PDO::FETCH_COLUMN);
        $q = $pdo->prepare('SELECT day_of_week, start_time, end_time FROM employee_availabilty WHERE employee_id = ?');
        $q->execute([$edytuj]);
        $godziny_db = [];
        foreach ($q->fetchAll() as $wpis) {
            $godziny_db[(int) $wpis['day_of_week']] = $wpis;
        }
    } else {
        $pracownicy = $pdo->query('SELECT p.id, p.active, p.description, u.name, u.surname, u.email, u.phone, (SELECT COUNT(*) FROM employee_equipment ee WHERE ee.employee_id = p.id) AS ile_sprzetu FROM employees p JOIN users u ON u.id = p.user_id ORDER BY u.surname, u.name')->fetchAll();
    }
} elseif ($sekcja === 'uzytkownicy') {
    $warunek = $szukaj !== '' ? 'WHERE name LIKE ? OR surname LIKE ? OR email LIKE ?' : '';
    $parametry_sql = $szukaj !== '' ? array_fill(0, 3, '%' . $szukaj . '%') : [];
    $razem = ile($pdo, "SELECT COUNT(*) FROM users $warunek", $parametry_sql);
    $q = $pdo->prepare("SELECT id, name, surname, email, phone, role, active, created_at FROM users $warunek ORDER BY id LIMIT " . NA_STRONE . ' OFFSET ' . $przesuniecie);
    $q->execute($parametry_sql);
    $uzytkownicy = $q->fetchAll();
} elseif ($sekcja === 'rezerwacje') {
    $filtr_data = z_adresu('data');
    $data_obiekt = DateTime::createFromFormat('Y-m-d', $filtr_data);
    if (!$data_obiekt || $data_obiekt->format('Y-m-d') !== $filtr_data) {
        $filtr_data = '';
    }
    $filtr_status = z_adresu('status');
    if (!isset($statusy[$filtr_status])) {
        $filtr_status = '';
    }
    $filtr_pracownik = liczba_z(z_adresu('pracownik'));

    $warunki = [];
    $parametry_sql = [];
    if ($filtr_data !== '') {
        $warunki[] = 'DATE(r.rental_start) <= ? AND DATE(r.rental_end) >= ?';
        $parametry_sql[] = $filtr_data;
        $parametry_sql[] = $filtr_data;
    }
    if ($filtr_status !== '') {
        $warunki[] = 'r.status = ?';
        $parametry_sql[] = $filtr_status;
    }
    if ($filtr_pracownik > 0) {
        $warunki[] = 'r.employee_id = ?';
        $parametry_sql[] = $filtr_pracownik;
    }
    $warunek = $warunki ? 'WHERE ' . implode(' AND ', $warunki) : '';
    $z_tabel = 'FROM reservations r
        JOIN equipment e ON e.id = r.equipment_id
        JOIN users k ON k.id = r.user_id
        JOIN employees pr ON pr.id = r.employee_id
        JOIN users p ON p.id = pr.user_id';
    $razem = ile($pdo, "SELECT COUNT(*) $z_tabel $warunek", $parametry_sql);
    $q = $pdo->prepare("SELECT r.id, r.rental_start, r.rental_end, r.status, r.comment, e.name AS sprzet, CONCAT(k.name, ' ', k.surname) AS klient, CONCAT(p.name, ' ', p.surname) AS pracownik $z_tabel $warunek ORDER BY r.rental_start DESC LIMIT " . NA_STRONE . ' OFFSET ' . $przesuniecie);
    $q->execute($parametry_sql);
    $rezerwacje = $q->fetchAll();
    $lista_pracownikow = $pdo->query("SELECT pr.id, CONCAT(u.name, ' ', u.surname) AS nazwa FROM employees pr JOIN users u ON u.id = pr.user_id ORDER BY u.surname")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel administratora — <?= h($sekcje[$sekcja]) ?></title>
    <link rel="stylesheet" href="../css/styl.css">
</head>
<body>
    <main class="szeroki">
        <h1>Panel administratora</h1>

        <nav class="menu">
            <?php foreach ($sekcje as $klucz => $etykieta): ?>
                <a href="?sekcja=<?= h($klucz) ?>"<?= $klucz === $sekcja ? ' class="aktywny"' : '' ?>><?= h($etykieta) ?></a>
            <?php endforeach; ?>
                | <a href="index.php?strona=profil">Mój profil</a>
        </nav>

        <form method="post" action="index.php">
            <input type="hidden" name="token" value="<?= h($_SESSION['token']) ?>">
            <button name="akcja" value="wyloguj">Wyloguj się</button>
        </form>

        <?php foreach ($bledy as $blad): ?>
            <p class="blad"><?= h($blad) ?></p>
        <?php endforeach; ?>

        <?php if (isset($_SESSION['sukces'])): ?>
            <p class="sukces"><?= h($_SESSION['sukces']) ?></p>
            <?php unset($_SESSION['sukces']); ?>
        <?php endif; ?>

        <?php if ($sekcja === 'dashboard'): ?>
            <h2>Dashboard</h2>
            <div class="karty">
                <?php foreach ($liczniki as $etykieta => $liczba): ?>
                    <div class="karta"><strong><?= h($liczba) ?></strong><?= h($etykieta) ?></div>
                <?php endforeach; ?>
            </div>

            <h3>Rezerwacje według statusu</h3>
            <ul>
                <?php foreach ($statusy as $klucz => $etykieta): ?>
                    <li><?= h($etykieta) ?>: <?= h($wg_statusu[$klucz] ?? 0) ?></li>
                <?php endforeach; ?>
            </ul>
            <p>„Rezerwacje na dziś” to rezerwacje, które trwają dzisiaj i nie są anulowane.</p>

        <?php elseif ($sekcja === 'kategorie'): ?>
            <h2>Kategorie sprzętu</h2>

            <div class="tabela">
                <table>
                    <tr>
                        <th>ID</th>
                        <th>Nazwa</th>
                        <th>Opis</th>
                        <th>Liczba sprzętu</th>
                        <th>Akcje</th>
                    </tr>
                    <?php foreach ($kategorie as $k): ?>
                        <tr>
                            <td><?= h($k['id']) ?></td>
                            <td><?= h($k['name']) ?></td>
                            <td><?= h($k['description']) ?></td>
                            <td><?= h($k['ile_sprzetu']) ?></td>
                            <td>
                                <a href="?sekcja=kategorie&amp;edytuj=<?= h($k['id']) ?>">Edytuj</a>
                                <form method="post" onsubmit="return confirm('Na pewno usunąć tę kategorię?');">
                                    <input type="hidden" name="token" value="<?= h($_SESSION['token']) ?>">
                                    <input type="hidden" name="id" value="<?= h($k['id']) ?>">
                                    <button class="maly usun" name="akcja" value="kategoria_usun">Usuń</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>

            <h3><?= $wiersz ? 'Edytuj kategorię' : 'Dodaj kategorię' ?></h3>
            <form method="post">
                <input type="hidden" name="token" value="<?= h($_SESSION['token']) ?>">
                <input type="hidden" name="id" value="<?= h($wiersz['id'] ?? 0) ?>">
                <label>Nazwa
                    <input name="nazwa" maxlength="100" value="<?= wart($stare, 'nazwa', $wiersz['name'] ?? '') ?>" required>
                </label>
                <label>Opis
                    <input name="opis" maxlength="1000" value="<?= wart($stare, 'opis', $wiersz['description'] ?? '') ?>">
                </label>
                <button name="akcja" value="kategoria_zapisz">Zapisz</button>
                <?php if ($wiersz): ?>
                    <a href="?sekcja=kategorie">Anuluj edycję</a>
                <?php endif; ?>
            </form>

        <?php elseif ($sekcja === 'sprzet'): ?>
            <h2>Sprzęt</h2>

            <form method="get" class="filtry">
                <input type="hidden" name="sekcja" value="sprzet">
                <label>Szukaj po nazwie
                    <input name="q" value="<?= h($szukaj) ?>">
                </label>
                <button>Szukaj</button>
                <a href="?sekcja=sprzet">Wyczyść</a>
            </form>

            <div class="tabela">
                <table>
                    <tr>
                        <th>ID</th>
                        <th>Nazwa</th>
                        <th>Kategoria</th>
                        <th>Cena za dobę</th>
                        <th>Kaucja</th>
                        <th>Aktywny</th>
                        <th>Akcje</th>
                    </tr>
                    <?php foreach ($sprzet as $s): ?>
                        <tr>
                            <td><?= h($s['id']) ?></td>
                            <td><?= h($s['name']) ?></td>
                            <td><?= h($s['kategoria']) ?></td>
                            <td><?= h($s['daily_price']) ?> zł</td>
                            <td><?= h($s['deposit']) ?> zł</td>
                            <td><?= $s['active'] ? 'tak' : 'nie' ?></td>
                            <td>
                                <a href="?sekcja=sprzet&amp;edytuj=<?= h($s['id']) ?>">Edytuj</a>
                                <form method="post">
                                    <input type="hidden" name="token" value="<?= h($_SESSION['token']) ?>">
                                    <input type="hidden" name="id" value="<?= h($s['id']) ?>">
                                    <button class="maly" name="akcja" value="sprzet_aktywnosc"><?= $s['active'] ? 'Dezaktywuj' : 'Aktywuj' ?></button>
                                </form>
                                <form method="post" onsubmit="return confirm('Na pewno usunąć ten sprzęt?');">
                                    <input type="hidden" name="token" value="<?= h($_SESSION['token']) ?>">
                                    <input type="hidden" name="id" value="<?= h($s['id']) ?>">
                                    <button class="maly usun" name="akcja" value="sprzet_usun">Usuń</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
            <?php linki_stron($razem, $obecna, ['sekcja' => 'sprzet', 'q' => $szukaj]); ?>

            <h3><?= $wiersz ? 'Edytuj sprzęt' : 'Dodaj sprzęt' ?></h3>
            <form method="post">
                <input type="hidden" name="token" value="<?= h($_SESSION['token']) ?>">
                <input type="hidden" name="id" value="<?= h($wiersz['id'] ?? 0) ?>">
                <label>Kategoria
                    <select name="kategoria" required>
                        <?php $wybrana = $stare['kategoria'] ?? ($wiersz['category_id'] ?? 0); ?>
                        <?php foreach ($lista_kategorii as $k): ?>
                            <option value="<?= h($k['id']) ?>"<?= (string) $k['id'] === (string) $wybrana ? ' selected' : '' ?>><?= h($k['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Nazwa
                    <input name="nazwa" maxlength="150" value="<?= wart($stare, 'nazwa', $wiersz['name'] ?? '') ?>" required>
                </label>
                <label>Opis
                    <input name="opis" maxlength="1000" value="<?= wart($stare, 'opis', $wiersz['description'] ?? '') ?>">
                </label>
                <label>Cena za dobę (zł)
                    <input name="cena" value="<?= wart($stare, 'cena', $wiersz['daily_price'] ?? '') ?>" required>
                </label>
                <label>Kaucja (zł)
                    <input name="kaucja" value="<?= wart($stare, 'kaucja', $wiersz['deposit'] ?? '0') ?>" required>
                </label>
                <?php
                if ($stare) {
                    $czy_aktywny = ($stare['aktywny'] ?? '') === '1';
                } else {
                    $czy_aktywny = $wiersz ? (bool) $wiersz['active'] : true;
                }
                ?>
                <label>
                    <input type="checkbox" name="aktywny" value="1"<?= $czy_aktywny ? ' checked' : '' ?>> Aktywny (widoczny dla klientów)
                </label>
                <button name="akcja" value="sprzet_zapisz">Zapisz</button>
                <?php if ($wiersz): ?>
                    <a href="?sekcja=sprzet">Anuluj edycję</a>
                <?php endif; ?>
            </form>

        <?php elseif ($sekcja === 'pracownicy' && $pracownik): ?>
            <h2>Pracownik: <?= h($pracownik['name'] . ' ' . $pracownik['surname']) ?></h2>
            <p>E-mail: <?= h($pracownik['email']) ?> | <a href="?sekcja=pracownicy">Wróć do listy</a></p>

            <form method="post">
                <input type="hidden" name="token" value="<?= h($_SESSION['token']) ?>">
                <input type="hidden" name="id" value="<?= h($pracownik['id']) ?>">

                <label>Opis
                    <input name="opis" maxlength="1000" value="<?= wart($stare, 'opis', $pracownik['description'] ?? '') ?>">
                </label>
                <label>
                    <input type="checkbox" name="aktywny" value="1"<?= $pracownik['active'] ? ' checked' : '' ?>> Aktywny
                </label>

                <h3>Obsługiwany sprzęt</h3>
                <?php foreach ($caly_sprzet as $s): ?>
                    <label>
                        <input type="checkbox" name="sprzet[]" value="<?= h($s['id']) ?>"<?= in_array($s['id'], $przypisane) ? ' checked' : '' ?>> <?= h($s['name']) ?>
                    </label>
                <?php endforeach; ?>

                <h3>Dostępność (godziny pracy)</h3>
                <div class="tabela">
                    <table>
                        <tr>
                            <th>Dzień</th>
                            <th>Pracuje</th>
                            <th>Od</th>
                            <th>Do</th>
                        </tr>
                        <?php foreach ($dni as $nr => $nazwa_dnia): ?>
                            <?php $wpis = $godziny_db[$nr] ?? null; ?>
                            <tr>
                                <td><?= h($nazwa_dnia) ?></td>
                                <td><input type="checkbox" name="dzien<?= $nr ?>" value="1"<?= $wpis ? ' checked' : '' ?>></td>
                                <td><input type="time" name="od<?= $nr ?>" value="<?= h($wpis ? substr($wpis['start_time'], 0, 5) : '09:00') ?>"></td>
                                <td><input type="time" name="do<?= $nr ?>" value="<?= h($wpis ? substr($wpis['end_time'], 0, 5) : '17:00') ?>"></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                <button name="akcja" value="pracownik_zapisz">Zapisz</button>
            </form>

        <?php elseif ($sekcja === 'pracownicy'): ?>
            <h2>Pracownicy</h2>

            <div class="tabela">
                <table>
                    <tr>
                        <th>ID</th>
                        <th>Imię i nazwisko</th>
                        <th>E-mail</th>
                        <th>Telefon</th>
                        <th>Sprzęt</th>
                        <th>Aktywny</th>
                        <th>Akcje</th>
                    </tr>
                    <?php foreach ($pracownicy as $p): ?>
                        <tr>
                            <td><?= h($p['id']) ?></td>
                            <td><?= h($p['name'] . ' ' . $p['surname']) ?></td>
                            <td><?= h($p['email']) ?></td>
                            <td><?= h($p['phone']) ?></td>
                            <td><?= h($p['ile_sprzetu']) ?></td>
                            <td><?= $p['active'] ? 'tak' : 'nie' ?></td>
                            <td>
                                <a href="?sekcja=pracownicy&amp;edytuj=<?= h($p['id']) ?>">Sprzęt i dostępność</a>
                                <form method="post">
                                    <input type="hidden" name="token" value="<?= h($_SESSION['token']) ?>">
                                    <input type="hidden" name="id" value="<?= h($p['id']) ?>">
                                    <button class="maly" name="akcja" value="pracownik_aktywnosc"><?= $p['active'] ? 'Dezaktywuj' : 'Aktywuj' ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>

            <h3>Dodaj pracownika</h3>
            <form method="post">
                <input type="hidden" name="token" value="<?= h($_SESSION['token']) ?>">
                <label>Imię
                    <input name="imie" maxlength="50" value="<?= wart($stare, 'imie') ?>" required>
                </label>
                <label>Nazwisko
                    <input name="nazwisko" maxlength="50" value="<?= wart($stare, 'nazwisko') ?>" required>
                </label>
                <label>E-mail
                    <input name="email" type="email" maxlength="100" value="<?= wart($stare, 'email') ?>" required>
                </label>
                <label>Telefon
                    <input name="telefon" maxlength="20" value="<?= wart($stare, 'telefon') ?>" required>
                </label>
                <label>Hasło (min. 8 znaków)
                    <input name="haslo" type="password" autocomplete="new-password" required>
                </label>
                <label>Opis
                    <input name="opis" maxlength="1000" value="<?= wart($stare, 'opis') ?>">
                </label>
                <button name="akcja" value="pracownik_dodaj">Dodaj pracownika</button>
            </form>

        <?php elseif ($sekcja === 'uzytkownicy'): ?>
            <h2>Użytkownicy</h2>

            <form method="get" class="filtry">
                <input type="hidden" name="sekcja" value="uzytkownicy">
                <label>Szukaj (imię, nazwisko, e-mail)
                    <input name="q" value="<?= h($szukaj) ?>">
                </label>
                <button>Szukaj</button>
                <a href="?sekcja=uzytkownicy">Wyczyść</a>
            </form>

            <div class="tabela">
                <table>
                    <tr>
                        <th>ID</th>
                        <th>Imię i nazwisko</th>
                        <th>E-mail</th>
                        <th>Telefon</th>
                        <th>Rola</th>
                        <th>Aktywny</th>
                        <th>Utworzono</th>
                        <th>Akcje</th>
                    </tr>
                    <?php foreach ($uzytkownicy as $u): ?>
                        <tr>
                            <td><?= h($u['id']) ?></td>
                            <td><?= h($u['name'] . ' ' . $u['surname']) ?></td>
                            <td><?= h($u['email']) ?></td>
                            <td><?= h($u['phone']) ?></td>
                            <td><?= h($role[$u['role']] ?? $u['role']) ?></td>
                            <td><?= $u['active'] ? 'tak' : 'nie' ?></td>
                            <td><?= h($u['created_at']) ?></td>
                            <td>
                                <?php if ((int) $u['id'] !== (int) $user['id']): ?>
                                    <form method="post">
                                        <input type="hidden" name="token" value="<?= h($_SESSION['token']) ?>">
                                        <input type="hidden" name="id" value="<?= h($u['id']) ?>">
                                        <button class="maly" name="akcja" value="uzytkownik_aktywnosc"><?= $u['active'] ? 'Dezaktywuj' : 'Aktywuj' ?></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
            <?php linki_stron($razem, $obecna, ['sekcja' => 'uzytkownicy', 'q' => $szukaj]); ?>

        <?php elseif ($sekcja === 'rezerwacje'): ?>
            <h2>Rezerwacje</h2>

            <form method="get" class="filtry">
                <input type="hidden" name="sekcja" value="rezerwacje">
                <label>Data (wypożyczenie trwa w tym dniu)
                    <input type="date" name="data" value="<?= h($filtr_data) ?>">
                </label>
                <label>Status
                    <select name="status">
                        <option value="">wszystkie</option>
                        <?php foreach ($statusy as $klucz => $etykieta): ?>
                            <option value="<?= h($klucz) ?>"<?= $klucz === $filtr_status ? ' selected' : '' ?>><?= h($etykieta) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Pracownik
                    <select name="pracownik">
                        <option value="">wszyscy</option>
                        <?php foreach ($lista_pracownikow as $p): ?>
                            <option value="<?= h($p['id']) ?>"<?= (int) $p['id'] === $filtr_pracownik ? ' selected' : '' ?>><?= h($p['nazwa']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button>Filtruj</button>
                <a href="?sekcja=rezerwacje">Wyczyść</a>
            </form>

            <div class="tabela">
                <table>
                    <tr>
                        <th>ID</th>
                        <th>Klient</th>
                        <th>Sprzęt</th>
                        <th>Pracownik</th>
                        <th>Od</th>
                        <th>Do</th>
                        <th>Uwagi</th>
                        <th>Status</th>
                    </tr>
                    <?php foreach ($rezerwacje as $r): ?>
                        <tr>
                            <td><?= h($r['id']) ?></td>
                            <td><?= h($r['klient']) ?></td>
                            <td><?= h($r['sprzet']) ?></td>
                            <td><?= h($r['pracownik']) ?></td>
                            <td><?= h(date('d.m.Y H:i', strtotime($r['rental_start']))) ?></td>
                            <td><?= h(date('d.m.Y H:i', strtotime($r['rental_end']))) ?></td>
                            <td><?= h($r['comment']) ?></td>
                            <td>
                                <form method="post">
                                    <input type="hidden" name="token" value="<?= h($_SESSION['token']) ?>">
                                    <input type="hidden" name="id" value="<?= h($r['id']) ?>">
                                    <select name="status">
                                        <?php foreach ($statusy as $klucz => $etykieta): ?>
                                            <option value="<?= h($klucz) ?>"<?= $klucz === $r['status'] ? ' selected' : '' ?>><?= h($etykieta) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="maly" name="akcja" value="rezerwacja_status">Zmień</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
            <?php linki_stron($razem, $obecna, ['sekcja' => 'rezerwacje', 'data' => $filtr_data, 'status' => $filtr_status, 'pracownik' => $filtr_pracownik ?: '']); ?>
        <?php endif; ?>
    </main>
</body>
</html>