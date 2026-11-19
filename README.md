# Wypożyczalnia sprzętu komputerowego
System rezerwacji online umożliwiający wypożyczanie sprzętu komputerowego (laptopów, projektorów, konsol i akcesoriów) przez klientów, z panelami dla pracowników wypożyczalni oraz administratora.

### Strona internetowa projektu
https://wypozyczalnia-komputerowa.wuaze.com

## Autorzy
*Michał Karczewski*

## Opis systemu
Aplikacja pozwala klientom przeglądać dostępny sprzęt komputerowy, wybierać konkretną pozycję, pracownika obsługującego wydanie/zwrot oraz termin wypożyczenia, a następnie tworzyć rezerwację. System sprawdza dostępność sprzętu w wybranym terminie, blokuje nakładające się rezerwacje oraz umożliwia zarządzanie ofertą i rezerwacjami przez administratora i pracowników.

### Pliki
`public/index.php` - obsługuje rejestrację, logowanie, role, profil i wylogowanie.

`public/admin.php` - panel administratora

`public/start.php` - to wspólny początek wszystkich stron aplikacji

`public/baza.php` - to tylko połączenie z bazą.

`public/rezerwacje_funkcje.php` - wspólne funkcje rezerwacji: godziny pracy, kolizje terminów, menu i kontrola roli

`public/oferta.php` - lista i szczegóły aktywnego sprzętu, filtr kategorii

`public/rezerwuj.php` - proces rezerwacji klienta (pracownik, data, godzina, potwierdzenie)

`public/moje_rezerwacje.php` - rezerwacje klienta i anulowanie

`public/pracownik.php` - panel pracownika: wizyty, szczegóły, zmiana statusu

`css/styl.css` - wygląd.

## Użytkownicy

### Role użytkowników
**Klient** - zakłada konto, przegląda ofertę sprzętu, tworzy i anuluje własne rezerwacje, edytuje profil.

**Pracownik** - obsługuje wydanie/zwrot przypisanego mu sprzętu, przegląda swoje rezerwacje na dziś, przyszłe i historyczne, zmienia status rezerwacji.

**Administrator** - zarządza użytkownikami, kategoriami, sprzętem, przypisaniami pracownik–sprzęt, dostępnością pracowników oraz wszystkimi rezerwacjami.

Rola jest zapisana w kolumnie `role` tabeli `users`.

| Rola | Wartość w bazie | Panel |
|---|---|---|
| Klient | `client` | `index.php?strona=klient` |
| Pracownik | `employee` | `index.php?strona=pracownik` |
| Administrator | `admin` | `index.php?strona=administrator` |

- Każde nowe konto założone przez formularz rejestracji dostaje rolę klienta.
- Rola jest odczytywana z bazy danych, a nie z formularza ani z adresu URL.
- Po zalogowaniu użytkownik trafia do panelu odpowiedniego dla swojej roli.
- Próba wejścia do cudzego panelu przez wpisanie adresu kończy się komunikatem „Brak uprawnień”.
- Niezalogowany użytkownik jest przekierowywany do logowania.

### Konta testowe

| Rola | E-mail | Hasło |
|---|---|---|
| Administrator | `admin@wypozyczalnia.pl` | `AdminTest123!` |
| Pracownik | `piotr.nowak@wypozyczalnia.pl` | `Pracownik123!` |
| Klient | `jan.wisniewski@gmail.com` | `KlientTest123!` |

To konta demonstracyjne, słóżą do testów.

## Obsługa witryny

### 1. Logowanie

1. Kliknij **Logowanie**.
2. Wpisz e-mail i hasło.
3. Kliknij **Zaloguj się**.

**Oczekiwany wynik:** otwiera się panel odpowiedni dla roli konta (nagłówek `„Panel: klient”`, `„Panel: pracownik”` lub `„Panel: administrator”`) i powitanie z imieniem i nazwiskiem.

<mark>Przy błędnym e-mailu lub haśle pojawia się komunikat „Błędny e-mail lub hasło albo nieaktywne konto.”.<mark>

### 2. Rejestracja

1. Kliknij **Rejestracja**.
2. Wpisz imię, nazwisko, telefon, e-mail, hasło i powtórzenie hasła.
3. Kliknij **Utwórz konto**.

**Oczekiwany wynik:** przejście do logowania z komunikatem `„Konto utworzone. Zaloguj się.”`.

<mark>Nowe konto zawsze dostaje rolę klienta.<mark>

**Błędne dane w rejestracji:**
| Co wpisać | Oczekiwany komunikat |
|---|---|
| E-mail, który już istnieje | „E-mail jest już zajęty.” |
| Dwa różne hasła | „Hasła są różne.” |
| Hasło krótsze niż 8 znaków | Komunikat o długości hasła |
| E-mail bez `@` | Komunikat o poprawnym e-mailu |
| Telefon z literami, np. `abcdefghi` | Komunikat o formacie telefonu |

### 3. Profil

1. Kliknij **Mój profil**.
2. Zmień imię, nazwisko lub telefon.
3. Kliknij **Zapisz zmiany**.

**Oczekiwany wynik:** komunikat `„Zapisano zmiany.”`, a po odświeżeniu strony widać nowe dane. E-mail jest widoczny, ale nie można go zmienić.

<mark>Puste lub błędne dane (np. telefon z literami) są odrzucane z komunikatem, a stare dane zostają zapisane.<mark>

### 4. Wylogowanie

1. Kliknij przycisk **Wyloguj się**.

**Oczekiwany wynik:** strona logowania zamiast profilu, bo sesja została zakończona.

### 6. Panel administratora

Zaloguj się jako administrator i kliknij **Przejdź do panelu zarządzania** (albo otwórz `admin.php`). Menu na górze zawiera sześć zakładek.

#### 6.1 Dashboard

Pokazuje liczbę klientów, pracowników, sprzętu, wszystkich rezerwacji i rezerwacji na dziś oraz podział rezerwacji według statusu. `„Rezerwacje na dziś"` to rezerwacje, które trwają dzisiaj i nie są anulowane.

#### 6.2 Kategorie

1. Wpisz nazwę (i opcjonalnie opis) w formularzu **Dodaj kategorię** i kliknij **Zapisz**.
2. Kliknij **Edytuj**, zmień dane i kliknij **Zapisz**.
3. Kliknij **Usuń** i potwierdź w oknie.

**Oczekiwany wynik:** komunikaty `„Zapisano kategorię."` i `„Usunięto kategorię."`. Kategoria, w której jest sprzęt, nie zostanie usunięta, a pojawi się komunikat `„Nie można usunąć kategorii, która ma sprzęt. Najpierw przenieś lub usuń sprzęt."`. Powtórzona nazwa daje komunikat `„Kategoria o takiej nazwie już istnieje."`.

#### 6.3 Sprzęt

1. Wybierz kategorię, wpisz nazwę, cenę za dobę (np. `45,50`) i kaucję, a następnie kliknij **Zapisz**.
2. **Edytuj** otwiera formularz z danymi do zmiany.
3. **Dezaktywuj** / **Aktywuj** zmienia widoczność sprzętu dla klientów.
4. **Usuń** usuwa sprzęt po potwierdzeniu.
5. Pole **Szukaj po nazwie** zawęża listę. Lista jest podzielona na strony po 10 pozycji.

**Oczekiwany wynik:** komunikaty `„Zapisano sprzęt."`, `„Zmieniono aktywność sprzętu."` i `„Usunięto sprzęt."`. Sprzęt, który ma rezerwacje, nie zostanie usunięty, a pojawi się komunikat `„Ten sprzęt ma rezerwacje, więc nie można go usunąć. Dezaktywuj go."`. Błędna cena (np. litery) jest odrzucana z komunikatem.

#### 6.4 Pracownicy

1. W formularzu **Dodaj pracownika** wpisz imię, nazwisko, e-mail, telefon, hasło (min. 8 znaków) i kliknij **Dodaj pracownika**. Powstaje konto z rolą pracownika.
2. Kliknij **Sprzęt i dostępność** przy pracowniku.
3. Zaznacz sprzęt, który pracownik obsługuje.
4. Dla każdego dnia tygodnia zaznacz **Pracuje** i ustaw godziny od–do. Koniec pracy musi być późniejszy niż początek.
5. Kliknij **Zapisz**.
6. Przycisk **Dezaktywuj** / **Aktywuj** na liście zmienia aktywność pracownika.

**Oczekiwany wynik:** `komunikaty „Dodano pracownika."`, `„Zapisano dane pracownika."` i `„Zmieniono aktywność pracownika."`. Po odświeżeniu strony edycji zaznaczony sprzęt i godziny są zapamiętane.

#### 6.5 Użytkownicy

Lista wszystkich kont z rolą, statusem aktywności i datą utworzenia. Pole **Szukaj** przeszukuje imię, nazwisko i e-mail. Lista jest podzielona na strony po 10 pozycji.

Przycisk **Dezaktywuj** blokuje konto, które nie może się wtedy zalogować. **Aktywuj** przywraca dostęp. Własnego konta nie można dezaktywować.

#### 6.6 Rezerwacje

1. Wybierz datę, status lub pracownika i kliknij **Filtruj**. Filtry można łączyć. Przycisk **Wyczyść** zeruje filtry.
2. Wybierz nowy status z listy w wierszu rezerwacji i kliknij **Zmień**.

**Dostępne statusy:** oczekująca, potwierdzona, zrealizowana, anulowana. Anulowanie to zmiana statusu na `„anulowana"`, dzięki czemu historia rezerwacji zostaje w bazie. Po zmianie pojawia się komunikat `„Zmieniono status rezerwacji."`.

### 7. Oferta sprzętu

1. Kliknij **Oferta** w menu. Strona jest dostępna także bez logowania.
2. Wybierz kategorię z listy i kliknij **Filtruj**.
3. Kliknij **Szczegóły** przy sprzęcie.

**Oczekiwany wynik:** lista zawiera tylko aktywny sprzęt z nazwą, kategorią, ceną za dobę i kaucją. Szczegóły pokazują opis i informację o dostępności. Zalogowany klient widzi dodatkowo link **Zarezerwuj**. Pracownik i administrator go nie widzą.

### 8. Rezerwacja sprzętu (klient)

Proces przebiega w kolejności: sprzęt, pracownik, data, godzina, potwierdzenie.

1. Zaloguj się jako klient, kliknij **Oferta** i **Zarezerwuj** przy wybranym sprzęcie.
2. Wybierz pracownika. Lista zawiera tylko osoby obsługujące ten sprzęt.
3. Wybierz datę odbioru i kliknij **Pokaż godziny**. Nad formularzem widać godziny pracy pracownika.
4. Wybierz godzinę odbioru z listy wolnych godzin oraz liczbę dób (1–14) i kliknij **Dalej**.
5. Sprawdź podsumowanie (sprzęt, pracownik, odbiór, zwrot, cena wynajmu, kaucja) i kliknij **Potwierdzam rezerwację**.

**Oczekiwany wynik:** przejście do „Moje rezerwacje" z komunikatem `„Rezerwacja została zapisana i czeka na potwierdzenie.”`. Rezerwacja ma status „oczekująca". Termin zwrotu to odbiór plus wybrana liczba dób o tej samej godzinie, a cena wynajmu to liczba dób razy cena za dobę.

**Kontrola terminu:** serwer sprawdza termin przy podsumowaniu i jeszcze raz tuż przed zapisem (w transakcji z blokadą wiersza sprzętu). Nie da się jej ominąć zmianą danych w formularzu ani zarezerwować tego samego terminu jednocześnie z dwóch kont.

| Sytuacja | Oczekiwany komunikat |
|---|---|
| Odbiór w przeszłości | „Nie można rezerwować terminu z przeszłości." |
| Odbiór poza godzinami pracy pracownika | „Odbiór (…) wypada poza godzinami pracy pracownika." |
| Zwrot w dniu lub o godzinie, gdy pracownik nie pracuje (np. piątek + 1 doba przy pracowniku bez soboty) | „Zwrot (…) wypada poza godzinami pracy pracownika. Wybierz inną liczbę dni." |
| Termin nachodzi na aktywną rezerwację tego sprzętu | „Ten sprzęt jest już zarezerwowany w wybranym terminie." |
| Dzień bez wolnych godzin | „Brak wolnych godzin odbioru w tym dniu. Wybierz inną datę." |

Kolizję wykrywa warunek: początek nowej rezerwacji jest przed końcem istniejącej **i** koniec nowej jest po początku istniejącej. Obejmuje on nakładanie częściowe i całkowite. Aktywne są rezerwacje ze statusem „oczekująca" i „potwierdzona", więc anulowane i zrealizowane nie blokują sprzętu.

Przykład dla istniejącej rezerwacji od D 10:00 do D+3 10:00:

| Nowy termin | Wynik |
|---|---|
| D−1 10:00 do D+1 10:00 | zablokowany |
| D 10:00 do D+3 10:00 | zablokowany |
| D+1 10:00 do D+2 10:00 | zablokowany |
| D+2 10:00 do D+4 10:00 | zablokowany |
| D−1 10:00 do D+4 10:00 | zablokowany |
| D−2 10:00 do D 10:00 | dozwolony |
| D+3 10:00 do D+4 10:00 | dozwolony |

Terminy kończące się dokładnie w chwili początku innej rezerwacji, i odwrotnie, są dozwolone, o ile mieszczą się w godzinach pracy pracownika.

### 9. Moje rezerwacje (klient)

1. Kliknij **Moje rezerwacje** w menu.
2. W sekcji **Aktualne i przyszłe** widzisz swoje aktywne rezerwacje: sprzęt, pracownika, odbiór, zwrot, cenę i status.
3. Aby anulować rezerwację, kliknij **Anuluj** i potwierdź w oknie.

**Oczekiwany wynik:** komunikat `„Rezerwacja została anulowana.”`, a rezerwacja przechodzi do sekcji **Zakończone i anulowane** ze statusem „anulowana". Rezerwację można anulować tylko wtedy, gdy jej odbiór jest w przyszłości. Klient widzi wyłącznie własne rezerwacje. Próba anulowania cudzej rezerwacji przez zmianę `id` w formularzu kończy się komunikatem `„Tej rezerwacji nie można anulować.”`.

### 10. Panel pracownika

1. Zaloguj się jako pracownik i kliknij **Moje wizyty**.
2. Rezerwacje są podzielone na trzy sekcje: **Dziś i w trakcie**, **Przyszłe** i **Historia**.
3. Kliknij **Szczegóły**, aby zobaczyć dane klienta (imię, nazwisko, telefon, e-mail), sprzęt, termin, cenę i uwagi.
4. Wybierz nowy status z listy i kliknij **Zmień**.

**Oczekiwany wynik:** komunikat `„Zmieniono status rezerwacji.”`. Zmiana jest widoczna także w panelu klienta i administratora.

Pracownik widzi i zmienia tylko rezerwacje przypisane do niego. Otwarcie cudzej rezerwacji (`pracownik.php?id=…`) pokazuje `„Nie masz dostępu do tej rezerwacji.”`. Przywrócenie anulowanej rezerwacji na aktywną jest możliwe tylko wtedy, gdy termin nie jest zajęty przez inną rezerwację (`„Nie można przywrócić rezerwacji: termin jest już zajęty przez inną rezerwację.”`).
