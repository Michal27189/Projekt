<?php
require __DIR__ . '/start.php';
require __DIR__ . '/rezerwacje_funkcje.php';

$id = (int) ($_GET['id'] ?? 0);
$kategoria = (int) ($_GET['kategoria'] ?? 0);

naglowek('Oferta sprzętu', $user ?? null);

if ($id > 0) {
    $s = $pdo->prepare(
        'SELECT q.*, c.name AS kategoria,
                (SELECT COUNT(*) FROM employee_equipment ee
                 JOIN employees e ON e.id = ee.employee_id
                 WHERE ee.equipment_id = q.id AND e.active = 1) AS pracownicy
         FROM equipment q JOIN equipment_categories c ON c.id = q.category_id
         WHERE q.id = ? AND q.active = 1'
    );
    $s->execute([$id]);
    $q = $s->fetch(PDO::FETCH_ASSOC);

    if (!$q) {
        echo '<p class="blad">Nie znaleziono takiego sprzętu.</p>';
    } else {
        echo '<h2>' . h($q['name']) . '</h2>';
        echo '<p>Kategoria: ' . h($q['kategoria']) . '</p>';
        echo '<p>' . h($q['description'] ?? '') . '</p>';
        echo '<p>Cena za dobę: <strong>' . zl((float) $q['daily_price']) . '</strong>, kaucja: ' . zl((float) $q['deposit']) . '</p>';
        if ((int) $q['pracownicy'] > 0) {
            echo '<p>Dostępność: sprzęt można zarezerwować.</p>';
            if (($user['role'] ?? '') === 'client') {
                echo '<p><a href="rezerwuj.php?sprzet=' . (int) $q['id'] . '">Zarezerwuj</a></p>';
            } elseif (!$user) {
                echo '<p>Aby zarezerwować, <a href="index.php?strona=logowanie">zaloguj się</a> jako klient.</p>';
            }
        } else {
            echo '<p>Dostępność: chwilowo brak pracownika obsługującego ten sprzęt.</p>';
        }
    }
    echo '<p><a href="oferta.php">Wróć do oferty</a></p>';
    stopka();
    exit;
}

$kategorie = $pdo->query('SELECT id, name FROM equipment_categories ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
echo '<form method="get" class="filtry"><label>Kategoria <select name="kategoria"><option value="0">Wszystkie</option>';
foreach ($kategorie as $k) {
    $zaznaczona = $kategoria === (int) $k['id'] ? ' selected' : '';
    echo '<option value="' . (int) $k['id'] . '"' . $zaznaczona . '>' . h($k['name']) . '</option>';
}
echo '</select></label><button type="submit">Filtruj</button></form>';

$sql = 'SELECT q.id, q.name, q.description, q.daily_price, q.deposit, c.name AS kategoria
        FROM equipment q JOIN equipment_categories c ON c.id = q.category_id
        WHERE q.active = 1';
$param = [];
if ($kategoria > 0) {
    $sql .= ' AND q.category_id = ?';
    $param[] = $kategoria;
}
$sql .= ' ORDER BY c.name, q.name';
$s = $pdo->prepare($sql);
$s->execute($param);
$lista = $s->fetchAll(PDO::FETCH_ASSOC);

if (!$lista) {
    echo '<p>Brak sprzętu w tej kategorii.</p>';
} else {
    echo '<div class="tabela"><table><tr><th>Sprzęt</th><th>Kategoria</th><th>Cena za dobę</th><th>Kaucja</th><th></th></tr>';
    foreach ($lista as $q) {
        echo '<tr><td>' . h($q['name']) . '</td><td>' . h($q['kategoria']) . '</td><td>' . zl((float) $q['daily_price'])
            . '</td><td>' . zl((float) $q['deposit']) . '</td><td><a href="oferta.php?id=' . (int) $q['id'] . '">Szczegóły</a></td></tr>';
    }
    echo '</table></div>';
}
stopka();
