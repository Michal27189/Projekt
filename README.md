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
