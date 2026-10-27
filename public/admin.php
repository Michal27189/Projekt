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
    </main>
</body>
</html>