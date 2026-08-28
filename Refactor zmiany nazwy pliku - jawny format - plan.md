# Refactor: jawny format zmiany nazwy pliku — plan

Kontynuacja refaktoru z „Refactor zmiany nazwy pliku - plan.md". Tamten etap
dodał rename per-metadane, ale **flow zgaduje** format docelowy (single-artist
vs various-artists, baseDir). Ten plan usuwa zgadywanie z warstwy zapisu.

## Cel

`TrackRenameService` ma **otrzymywać** format docelowy, a nie go dedukować.
Użytkownik decyduje na froncie: „tylko zapisz metadane" vs „zapisz i zmień
nazwę". Mechanizm wspólny dla plików w kolekcji i w incoming; różni się tylko
prezentacja opcji (dla kolekcji brak „przenieś do _zrobione").

Konkretny cel tej rozmowy: umożliwić zmianę nazwy **bezpośrednio na stronie
edycji utworu** (incoming i kolekcja). Zmiana nazwy z poziomu **listy** (modal +
AJAX) już istnieje i zostaje — chodzi o dołożenie równoważnej opcji do edycji.

**Jeden mechanizm backendowy (PHP) niezależnie od wejścia.** Wszystkie punkty
wejścia — modal listy, edycja incoming, edycja kolekcji, CLI — używają tego
samego serwisu zmiany nazwy. Serwis na podstawie przekazanych danych **i
kontekstu** (incoming / kolekcja) wykonuje operację albo ją **odrzuca**;
np. utworu z kolekcji nie da się przenieść do `_zrobione`/incoming, a utworu z
incoming nie wpuszcza się do kolekcji bez walidacji. Reguły dopuszczalności
egzekwuje `CollectionGuard` (już istnieje) — decyzja należy do serwisu, nie do
poszczególnych kontrolerów/UI. UI tylko różnicuje **prezentację** dostępnych
opcji.

## Zasada przewodnia (dlaczego to upraszcza)

Zgadywanie nie znika całkowicie — przy kolekcji sugestia „ten sam format co
teraz" nadal musi rozpoznać obecny wzorzec nazwy. Ale **przenosi się z warstwy
zapisu do warstwy prezentacji (sugestia)**:

- źle zgadnięta **sugestia** → user widzi i poprawia (samonaprawialne),
- źle zgadnięty **realny rename** → cichy błąd na dysku.

Format staje się jawnym wejściem operacji zapisu; rozpoznawanie jest tylko
podpowiedzią wstępnie zaznaczoną w UI.

## Stan zastany (fakty z kodu)

- `TrackRenameService::rename($track, $format, $metadata, $markAsReady)` —
  **ścieżka z jawnym formatem już istnieje** i jest używana przez CLI
  (`RenameTrackTask` z `--format`). Web jej nie używa.
- Web używa zgadującego `renameToCollectionLayout()` (+ `resolveCollectionLayout`,
  `collectionFilenameFormat`, `isVariousArtistsFilename`, `resolveCollectionTarget`).
- `modal-rename.twig` (Directory, zbiorczy `common.task.rename`) ma **3 presety**
  jako wzorce `%...%` + checkbox „oznacz jako gotowy"; custom zakomentowany:
  - `%artist% - %title%`
  - `%artist%/%album%/%artist% - %track_number% - %title%`
  - `%artist%/%album%/%track_number%. %artist% - %title%`
- **Kolekcja**: `Track\EditController::save` → `TrackUpdateService::update()`
  (zgaduje, czy rename jest potrzebny — `isRenameNeeded()` — i jaki format).
- **Incoming, strona edycji**: `IncomingTrack\EditController::save` woła
  `TrackMetadataWriter` bezpośrednio — zapisuje tylko tagi + opcjonalny calc,
  **nie zmienia nazwy**. To tutaj dokładamy rename.
- **Incoming, strona listy**: zmiana nazwy **już działa** — modal
  (`modal-rename.twig`) + akcja AJAX `common.task.rename` (Directory, na wielu
  `elements`). Zostaje; ma współdzielić słownik presetów (decyzja #6).
- **Kolekcja, strona edycji**: rename dzieje się automatycznie przy zmianie pól
  (`TrackUpdateService`, zgadywanie); brak jawnej opcji „zmień nazwę / nie".
- `TrackMetadataValidator` + guard w `TrackRenameService`
  (`Cannot prepare target filename: some metadata fields are empty (%s)`)
  chronią przed pustymi polami.
- `TrackFilenameSuggestion` istnieje, ale to heurystyka czyszczenia nazwy z
  surowego pliku (inny cel niż wybór presetu) — poza zakresem.
- `IndexingDateStrategy` + `IndexedDate` (moduł Collection/Task/Indexer)
  rozwiązują katalog Rok/Miesiąc — relevantne dla etapu B.

## Decyzje (podjęte)

| # | Decyzja |
|---|---------|
| 1 | **Teraz robimy „Zapisz i zmień nazwę" (rename w miejscu).** „Zapisz i dodaj do kolekcji" (przeniesienie incoming → kolekcja + Rok/Miesiąc) = etap dalszy (B). |
| 2 | Rok/Miesiąc oraz wybór Singles/Other = **opcje w UI** (sugestia + decyzja usera), bez zgadywania — dotyczy etapu B, oparte o `IndexingDateStrategy`/`IndexedDate`. |
| 3 | Sugestia formatu — reguły: patrz niżej. Zawsze tylko **sugestia**, user może wybrać inny preset. |
| 4 | Puste pola metadanych vs format: **odnotowane, na razie ignorujemy** — łapią to `TrackMetadataValidator` + guard w `TrackRenameService`. |
| 5 | „Inny format" = jeden z presetów. **Custom (free-text) zostaje wyłączony.** |
| 6 | Zbiorczy `common.task.rename` (Directory) zostaje; ma używać **tego samego słownika presetów** — spójność za darmo. |

## Reguły sugestii formatu

- **Utwór w kolekcji** → sugeruj **aktualny format** (rozpoznany z obecnej nazwy).
- **Utwór w incoming z dopasowaniem Beatport, single-track release (1/1)** →
  sugeruj format Singles (`Artist - NN - Title`).
- **Każdy inny przypadek** (incoming bez Beatport, lub >1 utworów) → `Artist - Title`.

> ⚠️ Do potwierdzenia: model `TrackMetadataSuggestions` **nie niesie liczby
> utworów w wydaniu** (jest pozycja `trackNumber`, brak „N z M"). Reguła „1/1"
> wymaga albo przeniesienia liczby utworów wydania przez pipeline sugestii
> (`TrackMetadataSuggestionsBuilder`/`BeatportTrackMetadataSuggestionsService`),
> albo doprecyzowania interpretacji (np. „jest dopasowanie Beatport → traktuj
> jak release"). Rozstrzygnąć na starcie etapu A, krok A4.

## Zakres

### Etap A — „Zapisz i zmień nazwę" (rename w miejscu) — TERAZ

Plik zostaje w swojej lokalizacji (kolekcja → ta sama; incoming → incoming lub
`_zrobione` gdy „oznacz jako gotowy"). **Bez** przenoszenia między Other/Singles
i bez budowania struktury Rok/Miesiąc.

**Backend (najpierw, testowalne bez UI):**

- **A1.** `UpdateTrackCommand`: dodać jawną intencję — tryb
  (`saveOnly` | `saveAndRename`), wybrany `format` (walidowany do zbioru
  dozwolonych presetów), `markAsReady` (tylko incoming). `fromRequest` parsuje
  nowe pola.
- **A2.** `TrackUpdateService::update()`: usunąć heurystykę `isRenameNeeded()`
  (F6) i dry-run przez `resolveCollectionTarget()`. Rename tylko gdy command
  mówi „rename", z jawnym `$format`. Dry-run konfliktu liczony z jawnego formatu.
- **A3.** Wspólny mechanizm dla incoming: incoming przechodzi przez ten sam
  wspólny serwis co kolekcja i modal listy (nie woła `TrackMetadataWriter`
  bezpośrednio z kontrolera). Rozgałęzienie po lokalizacji: kolekcja → zapis DB
  + reindex/clean; incoming → tylko FS (bez DB, bez reindeksu). `LocationKind`
  decyduje o gałęzi wykonawczej. Dopuszczalność operacji (np. kolekcja → ready
  zabronione, incoming → kolekcja tylko po walidacji) egzekwuje serwis przez
  `CollectionGuard` — odrzucenie jest po stronie serwisu, nie kontrolera/UI.
- **A4.** `FilenameFormatSuggester` (nowa mała klasa): zwraca preselektowany
  preset wg reguł z sekcji „Reguły sugestii". **Jedyne** miejsce, gdzie zostaje
  rozpoznawanie — w warstwie sugestii. (Zależy od rozstrzygnięcia „1/1", patrz ⚠️.)
- **A5.** Usunięcie zgadywania z `TrackRenameService`: `resolveCollectionLayout`,
  `collectionFilenameFormat`, `isVariousArtistsFilename`, `resolveCollectionTarget`,
  `renameToCollectionLayout`. Zostaje jawne `rename()` + `clean()` + `target()`.
- **A6.** Testy: usunąć testy zgadywania (`testCollectionFilenameFormat`,
  `testIsVariousArtistsFilename`); dodać testy `FilenameFormatSuggester`.
  Pozostałe (sanitize, removeEmptyDirectories, isDirectoryEmpty) bez zmian.
- **A7.** Aktualizacja `AGENTS.md`: format jawny + sugestia (usunąć opis
  wariantów zgadywanych z sekcji „Location rules & gotchas").

**UI (osobna sesja — decyzje UX odłożone):**

- **A8.** Format-picker na stronach edycji (Track + IncomingTrack), reużycie
  słownika presetów z `modal-rename.twig`. Preselekcja wg `FilenameFormatSuggester`.
- **A9.** Wybór „tylko zapisz" vs „zapisz i zmień nazwę"; dla incoming dodatkowo
  `mark_as_ready`; dla kolekcji bez tej opcji.

**Decyzje UX do podjęcia przed A8/A9 (odłożone):**
- checkbox + rozwijana lista presetów, czy przycisk otwierający modal (jak istniejący)?
- presety dla incoming rename-w-miejscu: warianty **flat** (`Artist - NN - Title`)
  czy **nested** (`Artist/Album/...`)? Nested tworzyłby podkatalogi w incoming.

### Etap B — „Zapisz i dodaj do kolekcji" — PÓŹNIEJ

- Wybór miejsca docelowego Singles vs Other (sugestia + decyzja usera).
- Rok/Miesiąc z `IndexingDateStrategy`/`IndexedDate` (opcja w UI, bez zgadywania).
- Przeniesienie incoming → kolekcja + indeksacja.
- (Dopiero tu formaty nested `%artist%/%album%/...` mają pełny sens.)

## Do skasowania (zgadywanie)

`TrackRenameService`: `resolveCollectionLayout`, `collectionFilenameFormat`,
`isVariousArtistsFilename`, `resolveCollectionTarget`, `renameToCollectionLayout`.
`TrackUpdateService`: `isRenameNeeded` + dry-run oparty o zgadywanie.
Testy zgadywania w `TrackRenameServiceTest`.

## Poza zakresem

- Finalne decyzje UX/UI (odłożone).
- „Zapisz i dodaj do kolekcji" (etap B).
- Custom (free-text) format.
- Refaktor `TrackFilenameSuggestion` (heurystyka czyszczenia surowej nazwy).

## Otwarte punkty / ryzyka

1. **Źródło „1/1" dla incoming** — patrz ⚠️ w „Reguły sugestii". Blokuje A4.
2. **Preset flat vs nested dla incoming** — decyzja UX (A8).
3. **Wspólny serwis vs rozdzielenie** (A3) — jeden serwis z rozgałęzieniem po
   `LocationKind`, czy wspólny rdzeń + dwa cienkie wejścia. Rozstrzygnąć przy A2/A3.
