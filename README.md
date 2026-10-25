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

`public/baza.php` - to tylko połączenie z bazą.

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

Oczekiwany wynik: otwiera się panel odpowiedni dla roli konta (nagłówek `„Panel: klient”`, `„Panel: pracownik”` lub `„Panel: administrator”`) i powitanie z imieniem i nazwiskiem.

<mark>Przy błędnym e-mailu lub haśle pojawia się komunikat „Błędny e-mail lub hasło albo nieaktywne konto.”.<mark>

### 2. Rejestracja

1. Kliknij **Rejestracja**.
2. Wpisz imię, nazwisko, telefon, e-mail, hasło i powtórzenie hasła.
3. Kliknij **Utwórz konto**.

Oczekiwany wynik: przejście do logowania z komunikatem `„Konto utworzone. Zaloguj się.”`.

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

Oczekiwany wynik: komunikat `„Zapisano zmiany.”`, a po odświeżeniu strony widać nowe dane. E-mail jest widoczny, ale nie można go zmienić.

<mark>Puste lub błędne dane (np. telefon z literami) są odrzucane z komunikatem, a stare dane zostają zapisane.<mark>

### 4. Wylogowanie

1. Kliknij przycisk **Wyloguj się**.

Oczekiwany wynik: strona logowania zamiast profilu, bo sesja została zakończona.
