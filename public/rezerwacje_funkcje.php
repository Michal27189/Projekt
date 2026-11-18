<?php
// Funkcje wspólne dla Etapu 4 (oferta, rezerwacje, panel klienta i pracownika)

const STATUSY_AKTYWNE = ['oczekujaca', 'potwierdzona'];
const STATUSY_WSZYSTKIE = ['oczekujaca', 'potwierdzona', 'zrealizowana', 'anulowana'];
const DNI_TYGODNIA = [0 => 'niedziela', 1 => 'poniedziałek', 2 => 'wtorek', 3 => 'środa', 4 => 'czwartek', 5 => 'piątek', 6 => 'sobota'];

// Przepuszcza tylko zalogowanych użytkowników z podaną rolą
function wymagaj_roli(?array $user, array $role): void
{
    if (!$user) {
        header('Location: index.php?strona=logowanie');
        exit;
    }
    if (!in_array($user['role'], $role, true)) {
        http_response_code(403);
        exit('Brak uprawnień.');
    }
}

function token_ok(): bool
{
    return isset($_POST['token'], $_SESSION['token'])
        && hash_equals($_SESSION['token'], (string) $_POST['token']);
}

function zl(float $kwota): string
{
    return number_format($kwota, 2, ',', ' ') . ' zł';
}

function liczba_dni(string $start, string $koniec): int
{
    $roznica = (new DateTime($koniec))->getTimestamp() - (new DateTime($start))->getTimestamp();
    return max(1, (int) ceil($roznica / 86400));
}

function data_pl(string $data): string
{
    return (new DateTime($data))->format('d.m.Y H:i');
}

function komunikat_flash(): void
{
    if (!empty($_SESSION['ok'])) {
        echo '<p class="sukces">' . h($_SESSION['ok']) . '</p>';
        unset($_SESSION['ok']);
    }
}

function naglowek(string $tytul, ?array $user): void
{
    echo '<!DOCTYPE html><html lang="pl"><head><meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . h($tytul) . '</title><link rel="stylesheet" href="css/styl.css"></head><body>';
    echo '<main class="szeroki"><nav class="menu"><a href="index.php">Strona główna</a> <a href="oferta.php">Oferta</a>';
    if ($user && $user['role'] === 'client') {
        echo ' <a href="moje_rezerwacje.php">Moje rezerwacje</a>';
    }
    if ($user && $user['role'] === 'employee') {
        echo ' <a href="pracownik.php">Moje wizyty</a>';
    }
    if ($user && $user['role'] === 'admin') {
        echo ' <a href="admin.php">Panel administratora</a>';
    }
    echo '</nav><h1>' . h($tytul) . '</h1>';
}

function stopka(): void
{
    echo '</main></body></html>';
}

// Godziny pracy pracownika w dniu tygodnia podanej daty
function godziny_pracy(PDO $pdo, int $pracownik, DateTime $kiedy): array
{
    $s = $pdo->prepare('SELECT start_time, end_time FROM employee_availabilty WHERE employee_id = ? AND day_of_week = ?');
    $s->execute([$pracownik, (int) $kiedy->format('w')]);
    return $s->fetchAll(PDO::FETCH_ASSOC);
}

function w_godzinach_pracy(PDO $pdo, int $pracownik, DateTime $kiedy): bool
{
    $godzina = $kiedy->format('H:i:s');
    foreach (godziny_pracy($pdo, $pracownik, $kiedy) as $g) {
        if ($godzina >= $g['start_time'] && $godzina <= $g['end_time']) {
            return true;
        }
    }
    return false;
}

// Zasada nakładania: nowy.start < istniejący.koniec ORAZ nowy.koniec > istniejący.start
function kolizja_sprzetu(PDO $pdo, int $sprzet, DateTime $start, DateTime $koniec, int $pomin_id = 0): bool
{
    $s = $pdo->prepare(
        "SELECT COUNT(*) FROM reservations
         WHERE equipment_id = ? AND id <> ? AND status IN ('oczekujaca', 'potwierdzona')
           AND rental_start < ? AND rental_end > ?"
    );
    $s->execute([$sprzet, $pomin_id, $koniec->format('Y-m-d H:i:s'), $start->format('Y-m-d H:i:s')]);
    return (int) $s->fetchColumn() > 0;
}

// Zwraca tekst błędu albo null, gdy termin jest poprawny
function sprawdz_termin(PDO $pdo, int $sprzet, int $pracownik, DateTime $start, DateTime $koniec): ?string
{
    if ($start <= new DateTime()) {
        return 'Nie można rezerwować terminu z przeszłości.';
    }
    if ($koniec <= $start) {
        return 'Zwrot musi być później niż odbiór.';
    }

    $s = $pdo->prepare(
        'SELECT 1 FROM employee_equipment ee
         JOIN employees e ON e.id = ee.employee_id
         JOIN users u ON u.id = e.user_id
         JOIN equipment q ON q.id = ee.equipment_id
         WHERE ee.employee_id = ? AND ee.equipment_id = ?
           AND e.active = 1 AND u.active = 1 AND q.active = 1'
    );
    $s->execute([$pracownik, $sprzet]);
    if (!$s->fetchColumn()) {
        return 'Wybrany pracownik nie obsługuje tego sprzętu.';
    }

    if (!w_godzinach_pracy($pdo, $pracownik, $start)) {
        return 'Odbiór (' . DNI_TYGODNIA[(int) $start->format('w')] . ' ' . $start->format('H:i') . ') wypada poza godzinami pracy pracownika.';
    }
    if (!w_godzinach_pracy($pdo, $pracownik, $koniec)) {
        return 'Zwrot (' . DNI_TYGODNIA[(int) $koniec->format('w')] . ' ' . $koniec->format('H:i') . ') wypada poza godzinami pracy pracownika. Wybierz inną liczbę dni.';
    }
    if (kolizja_sprzetu($pdo, $sprzet, $start, $koniec)) {
        return 'Ten sprzęt jest już zarezerwowany w wybranym terminie.';
    }
    return null;
}

// Wolne godziny odbioru w danym dniu (co 60 minut w godzinach pracy)
function wolne_odbiory(PDO $pdo, int $sprzet, int $pracownik, DateTime $dzien): array
{
    $s = $pdo->prepare(
        "SELECT rental_start, rental_end FROM reservations
         WHERE equipment_id = ? AND status IN ('oczekujaca', 'potwierdzona') AND rental_end > ?"
    );
    $s->execute([$sprzet, $dzien->format('Y-m-d 00:00:00')]);
    $zajete = $s->fetchAll(PDO::FETCH_ASSOC);

    $teraz = new DateTime();
    $wynik = [];
    foreach (godziny_pracy($pdo, $pracownik, $dzien) as $g) {
        $t = new DateTime($dzien->format('Y-m-d') . ' ' . $g['start_time']);
        $do = new DateTime($dzien->format('Y-m-d') . ' ' . $g['end_time']);
        while ($t <= $do) {
            $ok = $t > $teraz;
            $tekst = $t->format('Y-m-d H:i:s');
            foreach ($zajete as $z) {
                if ($tekst >= $z['rental_start'] && $tekst < $z['rental_end']) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                $wynik[] = $t->format('H:i');
            }
            $t->modify('+60 minutes');
        }
    }
    return array_values(array_unique($wynik));
}
