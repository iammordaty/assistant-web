# Plan refactoru — zmiana nazwy pliku utworu + sprzątanie pustych katalogów

Cel: metoda `save()` w edycji tracka ma zmienić nazwę pliku mp3 zgodnie z metadanymi (tagami ID3v2) oraz posprzątać (usunąć) katalogi, które pozostały puste po tej zmianie (np. po zmianie nazwy albumu albo artysty). Plan zbiera wszystkie znane błędy logiczne, runtime'owe i architektoniczne oraz opisuje docelowy flow.

Pliki, których dotyczy refactor:
- `src/Assistant/Module/Track/Controller/Track/EditController.php` (metoda `save`)
- `src/Assistant/Module/Track/Controller/IncomingTrack/EditController.php`
- `src/Assistant/Module/Track/Extension/TrackRenameService.php`
- `src/Assistant/Module/Track/Extension/TrackLocationArbiter.php`
- `src/Assistant/Module/Track/Model/Track.php`, `Model/IncomingTrack.php`
- `src/Assistant/Module/Track/Extension/TrackMetadataFields.php`

Kolejność: **najpierw błędy dotyczące flow i architektury**, potem błędy logiczne/runtime, na końcu kosmetyka.

> **Uwaga o numerach linii:** kotwicz się **nazwami metod**, nie numerami linii. Poniżej lokalizacje orientacyjne (mogą się przesunąć po edycjach):
> - `Track\EditController::save()` — `var_dump`+`exit` w bloku `catch` (~100–105); `BackgroundProcess` (~107–114); zapis GUID (~116–120); blok rename (~124–165); `collection:clean` (~147–155); `collection:index` finalny (~167–172); refetch tracka po ścieżce (~176).
> - `TrackRenameService::rename()` re-analiza pliku (~45–48); `move()`: `isSingle` (~116), `file_exists` throw (~130), `mkdir(...,0777,true)` (~139), `rename` (~146), `rmdir` leftoverów (~154–161); `str_contains(target,'%')`/regex (~75–84); `calculateLeftoverPaths` (~182–204).

# Część I — flow i architektura (priorytet najwyższy)

## F1. Rename przez lokalizację/nazwę tymczasową — odporność na filesystemy case-insensitive ✅ ZROBIONE (etap 1)

**Problem:** Na filesystemach niewrażliwych na wielkość liter (APFS domyślnie, HFS+, NTFS) `rename('/x/artist - title.mp3', '/x/Artist - Title.mp3')` — gdzie zmieniła się **wyłącznie wielkość liter** — może się nie powieść albo zachować niedeterministycznie (target "już istnieje", bo z punktu widzenia FS to ta sama ścieżka). To realny scenariusz: użytkownik poprawia `artist` z `"metallica"` na `"Metallica"`.

**Rozwiązanie — dwuetapowy rename przez plik tymczasowy:** Plik mp3 musi najpierw trafić do **lokalizacji tymczasowej (lub pod tymczasową nazwą)**, a dopiero potem do docelowej. Dzięki temu zmiana samej wielkości liter przechodzi przez pośredni krok, gdzie ścieżki źródłowa i pośrednia realnie się różnią.

```php
// w TrackRenameService::move() — zamiast pojedynczego rename():
// UWAGA: katalog docelowy ($target->getPath()) musi już istnieć (mkdir z F9 wykonać PRZED tym krokiem).
$tmp = $target->getPath() . '/.rename-' . bin2hex(random_bytes(8)) . '.tmp';

if (!@rename($source->getPathname(), $tmp)) {
    throw new \RuntimeException("Rename step 1 (to tmp) failed: {$source->getPathname()}");
}
if (!@rename($tmp, $target->getPathname())) {
    @rename($tmp, $source->getPathname()); // rollback do stanu wyjściowego
    throw new \RuntimeException("Rename step 2 (to target) failed: {$target->getPathname()}");
}
```

Uwaga: plik tymczasowy powinien powstawać w tym samym katalogu/wolumenie co **docelowy** (żeby `rename` był atomowym move w obrębie FS, a nie kopiowaniem między wolumenami) — dlatego w kodzie wyżej `$tmp` budujemy z `$target->getPath()`, **nie** `$source->getPath()` (przy zmianie samego artysty/albumu source i target są w różnych katalogach). Kolejność jest krytyczna: najpierw `mkdir` katalogu docelowego (F9), dopiero potem dwuetapowy rename. Krok pośredni obejmujemy rollbackiem (patrz F9).

**Powiązane:** kolizja nazw — sprawdzenie `file_exists($target)` musi rozróżniać przypadek "to ten sam plik, tylko inna wielkość liter" (dozwolone) od "to inny, istniejący plik" (konflikt). To bezpośrednio koliduje z dry-runem z F3: na FS case-insensitive `file_exists('/x/Artist - Title.mp3')` zwróci `true`, gdy różni się **tylko** wielkość liter (bo to ten sam inode) — czyli goły warunek `if (file_exists($target))` z F3 **fałszywie** zgłosi konflikt dla dokładnie tego scenariusza, który F1 ma naprawić. Rozróżnienie musi być realne, np.:

```php
$isSamePhysicalFile = file_exists($target->getPathname())
    && realpath($source->getPathname()) === realpath($target->getPathname());
$isRealConflict = file_exists($target->getPathname()) && !$isSamePhysicalFile;
```

Obecny `move()` robi `throw` na gołym `file_exists()` — więc **dziś** zmiana samej wielkości liter w ogóle się nie uda ("Target already exists"). To trzeba rozwiązać razem, nie osobno w F1 i F3.

**Uwaga o kolizji z aktualnym FS:** repo działa na Dockerze (wolumen `/collection`), więc realne zachowanie zależy od FS hosta (APFS na macOS host → case-insensitive). Warto zweryfikować na docelowym środowisku, bo od tego zależy priorytet F1.

## F2. Brak atomowości / koordynacji całej operacji ✅ ZROBIONE (etap 4)

**Problem:** `save()` wykonuje sekwencyjnie skutki uboczne bez koordynacji i bez rollbacku:

```php
$this->id3Adapter->writeMetadata($metadata);   // 1) zapis ID3 w pliku
$this->trackService->save($track);              // 2) zapis GUID-a w DB
$file = $this->trackRenameService->rename(...); // 3) mkdir + rename + rmdir
$this->trackService->save($track);              // 4) zapis nowej ścieżki w DB
shell_exec('... collection:clean ...');         // 5) sprzątanie pustych katalogów
shell_exec('... collection:index ...');         // 6) reindex
```

Jeśli krok 3 padnie po 1 → plik ma nowe tagi, starą nazwę, niespójną DB. Jeśli 4 padnie po 3 → plik w nowej lokalizacji, w DB stara ścieżka.

**Rozwiązanie:**
- Jeden koordynator `TrackUpdateService::update(Track, UpdateTrackCommand): UpdateResult` z jasną kolejnością i kompensacją (rollback `rename`, gdy `trackService->save` rzuci wyjątek).
- Zapis do DB **po** udanej operacji na FS, nie pomiędzy.
- `collection:clean` / `collection:index` zawsze przez kolejkę/`BackgroundProcess` (patrz F8).

**Docelowy szkielet flow:**

```
Controller::save
 ├── UpdateTrackCommand::fromRequest        (walidacja + normalizacja)   → F12
 ├── TrackUpdateService::update($track, $cmd)
 │     ├── Id3Writer::write($track->getFile(), $cmd->toId3())            // tagi
 │     ├── TargetPathPlanner::plan($track, $cmd, LocationKind)           // czysta fn → F3
 │     ├── if (planned !== current) FileMover::move(...) → RenameResult  // F1, F5, F9
 │     ├── TrackRepository::save($track->withFile(...)->withGuid(...))   // JEDEN zapis → F10
 │     └── return UpdateResult { newTrack, createdPaths, leftoverPaths, warnings }
 ├── AsyncJobs::dispatch(reindex(createdPaths))                          // F8
 ├── AsyncJobs::dispatch(cleanLeftovers(leftoverPaths))                  // F7, F8
 ├── if ($cmd->calculateAudioData) AsyncJobs::dispatch(calculateAudioData(newPath)) // B1
 ├── flash success / warningi                                           // B8
 └── PRG redirect na track.track.index
```

## F3. Waliduj / oblicz docelową ścieżkę zanim cokolwiek zmienisz (dry-run) ✅ ZROBIONE (etap 4)

**Problem:** metadane są zapisywane do pliku, zanim wiadomo, czy rename w ogóle się uda (np. konflikt nazwy). Po nieudanym rename plik ma już nowe tagi, ale starą nazwę.

**Rozwiązanie:** `TrackRenameService` dostaje metodę liczącą docelową ścieżkę bez efektów ubocznych (bez `mkdir`/`rename`/`rmdir`):

```php
public function resolveTargetPathname(Track|IncomingTrack $track, string $format, array $metadata): SplFileInfo
{
    // cała logika budowania ścieżki + sanitize, BEZ mkdir/rename/rmdir
}
```

```php
$target = $this->trackRenameService->resolveTargetPathname($track, $format, $metadata);
if (file_exists($target->getPathname()) /* i to inny plik niż źródło — patrz F1 */) {
    // obsłuż konflikt ZANIM cokolwiek zapiszesz
}
$this->id3Adapter->writeMetadata($metadata);
$this->trackRenameService->rename(...);
```

Dodatkowo: **weryfikacja po rename**, że plik docelowy istnieje:

```php
if (!file_exists($file->getPathname())) {
    throw new \RuntimeException('Rename reported success but target file does not exist');
}
```

## F4. `rename()` czyta metadane z pliku zamiast z requestu — rozjazd źródła prawdy ✅ ZROBIONE (etap 3)

**Problem:** kontroler buduje `$metadata` z POST i woła `writeMetadata()`. Następnie `TrackRenameService::rename()` **ponownie** analizuje plik i z tych tagów buduje nazwę:

```php
// TrackRenameService::rename()
$metadata = $this->id3Adapter->setFile($track->getFile())->analyze()->getMetadata();
```

Jeśli `writeMetadata()` zapisze częściowo (warningi GetID3 są częste), nazwa pliku rozjedzie się z intencją użytkownika. W skrajnym przypadku nazwa powstaje z mieszanki starych i nowych danych.

**Dodatkowy kontekst:** `id3Adapter` jest wstrzykiwany jako jedna (współdzielona) instancja i jest **stanowy** — kontroler robi na nim `setFile()` + `writeMetadata()`, a `rename()` na **tym samym** obiekcie robi `setFile()` + `analyze()`. To ta sama klasa problemów co stanowość `TrackRenameService` z F5: stan przenosi się między wywołaniami.

**Rozwiązanie:** przekazywać do `rename()` gotowy zestaw pól (DTO/`UpdateTrackCommand`), a nie czytać plik ponownie. Źródłem prawdy jest „command", nie dysk.

```php
public function rename(Track|IncomingTrack $track, string $format, array $metadata, bool $markAsReady): RenameResult
```

## F5. `TrackRenameService` jest stanowy — zwracaj `RenameResult` ✅ ZROBIONE (etap 3)

**Problem:** serwis trzyma stan w polach instancji:

```php
private array $logContext = [];
private array $leftoverPaths = [];
private array $createdPaths = [];
```

`getLeftoverPaths()`/`getCreatedPaths()` zwracają wynik ostatniego wywołania. W DI jako singleton → nieprzewidywalne przy wielu wywołaniach / równolegle. Dodatkowo `move()` resetuje `leftoverPaths`/`createdPaths`, ale **nie resetuje** `logContext` — zostają śmieci z poprzedniego wywołania.

**Rozwiązanie:** zwracać obiekt wyniku, usunąć gettery i pola stanu:

```php
final readonly class RenameResult
{
    public function __construct(
        public SplFileInfo $file,
        public array $createdPaths,
        public array $leftoverPaths,
    ) {}
}
```

## F6. Logika `isSingle`/format zduplikowana i w kontrolerze

**Problem:** decyzja o formacie i o „single" jest w dwóch miejscach:
- `Track\EditController` — dobór formatu nazwy,
- `TrackRenameService::move()` — `baseDir` i czy sprzątać.

```php
$isSingle = str_contains($track->getFile()->getPathname(), '/collection/Singles');
$format = $isSingle
    ? '%artist%/%album%/%artist% - %track_number% - %title%'
    : '%artist% - %title%';
```

Kontroler nie powinien znać struktury katalogów. Format zaszyty jako literał, dobierany po ścieżce źródłowej, nie po logicznym typie tracka.

**Rozwiązanie:**
- Przenieść decyzję o `LocationKind` i formacie do jednego miejsca (`TrackLocationArbiter` — w kodzie jest już komentarz „Warunek do Arbitra"). Uwaga: sam arbiter ma dziś błąd — patrz **F14**.
- Format deklaratywny (konfig/DI per `LocationKind`), nie literał.
- Kryterium „czy renamować": zbudować `DesiredFilename` (czysta funkcja `(Track, Command, LocationKind) → Path`) i porównać z bieżącą ścieżką. Rename tylko gdy `desired !== current`. To eliminuje zwidy „target already exists" i zbędne rename przy różnicach po `trim()`.

## F7. Cleanup: podwójna odpowiedzialność `rmdir()` + `collection:clean`

**Problem:** `move()` usuwa katalogi z FS:

```php
foreach ($this->leftoverPaths as $leftoverPath) {
    rmdir($leftoverPath);
}
```

a potem kontroler woła `collection:clean` na **tych samych** ścieżkach:

```php
foreach ($this->trackRenameService->getLeftoverPaths() as $leftoverPath) {
    $command = sprintf('php %s/bin/console.php collection:clean "%s"',
        $this->config->get('base_dir'), $leftoverPath);
    shell_exec($command);
}
```

Katalog już nie istnieje w momencie `collection:clean` → jeśli komenda wymaga istniejącego katalogu, kończy się błędem. Ryzyko „duchów" w DB, jeśli `rmdir` się uda, a `clean` nie.

> **Uwaga (patrz F13):** w praktyce ten „podwójny cleanup" **dziś w ogóle nie zachodzi**, bo warunek uruchamiający `rmdir` sprawdza lokalizację źródła **po** jego przeniesieniu i zawsze wychodzi `false`. Realny objaw jest więc odwrotny: puste katalogi nie są sprzątane wcale. Framing „konflikt dwóch mechanizmów" jest teoretyczny — naprawiając F13 doprowadzamy cleanup do działania, a wtedy F7 (jedna odpowiedzialność za usunięcie) staje się realnie potrzebny.

**Rozwiązanie:** jedna odpowiedzialność za usunięcie (FS + DB w jednej operacji). Albo `move()` usuwa i kontroler tylko indeksuje, albo `move()` tylko raportuje ścieżki, a czyści je kontroler/serwis. `rmdir` musi sprawdzać wynik i logować:

```php
if (!@rmdir($path)) {
    $this->logger->warning('Failed to remove empty directory', ['path' => $path]);
}
```

## F8. `shell_exec` — synchroniczny, blokujący request, bez obsługi błędów i z ręcznym escapingiem

**Problem:** `collection:clean` i `collection:index` (i częściowo `calculate-audio-data`) idą przez `shell_exec` — synchronicznie blokują request HTTP, nie sprawdzają wyniku (`shell_exec` zwraca `null` przy błędzie), a argumenty są escapowane ręcznie (`sprintf('"%s"', $path)` — rozjazd przy `"` w ścieżce). `php /data/bin/console.php` bywa zahardcodowane, raz z `base_dir`, raz nie.

> **⚠️ To jest też podatność, nie tylko brzydki kod — patrz B11 (command injection).** Ręczny escaping `"%s"` na ścieżce pochodzącej z metadanych podanych przez użytkownika pozwala na wstrzyknięcie polecenia.

**Rozwiązanie:**
- Wywoływać `collection:clean`/`collection:index` bezpośrednio jako serwisy PHP z kontenera DI, albo asynchronicznie przez `BackgroundProcess`/kolejkę (jak `calculate-audio-data`).
- `escapeshellarg()` zamiast ręcznego cudzysłowu (rozwiązuje też B11).
- Ujednolicić ścieżkę do `console.php` (jedno źródło, `base_dir`).
- Wydzielić fasadę `ConsoleCommandRunner` z `runAsync()` / `runSync()` (sprawdza kod wyjścia).

**⚠️ Konflikt z flow przekierowania (błąd logiczny w planie):** obecny kod na końcu `save()` **ponownie pobiera track z DB po ścieżce** i z niego bierze GUID do redirectu:

```php
$track = $this->trackService->getByPathname($trackPathname); // po SYNCHRONICZNYM reindeksie
$route = Route::create('track.track.index')->withParams([ 'guid' => $track->getGuid() ]);
```

Działa to **tylko dlatego**, że `collection:index` idzie przez blokujący `shell_exec` — zanim dojdzie do refetchu, DB ma już nowe dane i nowy GUID. Jeśli (zgodnie z F8) reindex stanie się **asynchroniczny**, ten refetch zwróci **stary** wiersz (albo `null` → `$track->getGuid()` na `null` = błąd 500) → redirect na nieaktualny/nieistniejący GUID → 404. Dlatego async reindex wymaga, aby **GUID i docelowy URL były policzone w procesie** (w `UpdateResult`, patrz F2/F10), a nie odczytane z DB po reindeksie. Te trzy zmiany (F8 async + F10 GUID + F2 `UpdateResult`) muszą wejść **razem** — nie da się zrobić F8 w izolacji bez zepsucia redirectu.

## F9. Brak rollbacku w `move()` — osierocone katalogi po nieudanym rename ✅ ZROBIONE (etap 1)

**Problem:**

```php
if (!file_exists($target->getPath()) && !mkdir($target->getPath(), 0777, true)) { ... }
if (rename($source->getPathname(), $target->getPathname()) === false) {
    throw new \RuntimeException(...);
}
```

Jeśli `mkdir` utworzył nową strukturę, a `rename` padnie — puste katalogi zostają.

**Rozwiązanie:** zapamiętać listę katalogów utworzonych w tym wywołaniu (jest już do tego `calculateNonExistentPaths()` → `createdPaths`) i posprzątać je w `catch` (cofać tylko te, których wcześniej nie było). Spójne z dwuetapowym rename z F1 (rollback pliku tymczasowego).

## F10. Zapis do DB rozłożony na dwa momenty + niejasny GUID ✅ ZROBIONE (etap 4)

> **Decyzja (etap 4):** GUID = **podgląd, ignoruj POST**. Usunięto blok `withGuid($postData['guid'])` z kontrolera — GUID i tak jest pochodną artist+title i zostaje wygenerowany przez reindex. Zapis do DB jest teraz **pojedynczy**, w `TrackUpdateService::update()`, po udanym rename (z kompensacją: przywrócenie pliku gdy `trackService->save()` rzuci). **Uwaga:** redirect nadal opiera się o refetch po **synchronicznym** reindeksie — to celowe do czasu etapu 6 (F8), gdzie async reindex wymaga policzenia GUID/URL w procesie.

**Problem:** GUID zapisywany osobno, potem track zapisywany ponownie z nową ścieżką. Jeśli drugi zapis się nie wykona → DB ma nowy GUID i starą ścieżkę.

```php
if ($postData['guid'] !== $track->getGuid()) {
    $track = $track->withGuid($postData['guid']);
    $this->trackService->save($track);
}
```

Dodatkowo niejasne, czy GUID jest pochodną artist+title (sugeruje to komentarz przy redirectcie), czy niezależnym identyfikatorem — reindex może nadpisać GUID.

**Ważny kontekst — czym naprawdę jest „zapis do DB" tutaj:** model `Track` **nie ma** setterów `withArtist/withTitle/withAlbum/withTrackNumber/withPublisher` (są tylko `withGuid`, `withFile`, `withPathname`, `withYear`, `withGenre`, `withBpm`, `withInitialKey`...). Zmienione artist/title/album z POST **nie trafiają do DB przez `trackService->save()`** — obiekt `Track` nadal niesie **stare** wartości tych pól, więc `save()` (który zapisuje pełne DTO) utrwala starą wartość artist/title/album, a realnie zmienia tylko GUID i ścieżkę. Nowe metadane lądują w DB dopiero przez `collection:index`, który **czyta świeże tagi ID3 z pliku**. To znaczy, że „jeden zapis DB" z tego punktu i tak nie niesie pełnej prawdy o utworze — źródłem prawdy dla pól metadanych jest reindex. Refactor musi to jawnie uwzględnić (albo dorobić settery i zapisywać komplet, albo świadomie zostawić reindex jako mechanizm utrwalania metadanych — ale wtedy patrz konflikt z F8).

**Rozwiązanie:**
- Zebrać wszystkie zmiany w `$track` i zapisać **raz**, po udanych operacjach FS.
- Wyklarować semantykę GUID: albo pole w formularzu to podgląd (ignoruj POST, generuj po renamie), albo GUID jest niezależny (zapis po renamie, nie przed). Komentarz przy redirectcie („zmienił się artysta → zmienił się GUID") sugeruje, że GUID jest **pochodną** artist+title generowaną przez reindex — jeśli tak, to `withGuid($postData['guid'])` zapisuje wartość, którą reindex i tak nadpisze; wtedy pole GUID z formularza jest tylko podglądem i POST należy ignorować.

## F11. Wspólny kod dla `Track` i `IncomingTrack`

**Problem:** `Track\EditController::save()` i `IncomingTrack\EditController::save()` są w ~90% klonem (budowa `$metadata`, filtrowanie `empty()`, merge z istniejącymi `initial_key`/`bpm`, zapis ID3, `var_dump`+`exit`, `calculate-audio-data`). Wszystkie błędy B6/B8/F12 dotyczą **obu** kontrolerów.

**Rozwiązanie:** wyodrębnić `TrackMetadataWriter` (id3 + opcjonalny `task:calculate-audio-data`) i użyć w obu kontrolerach. Logika rename wg formatu kolekcji zostaje tylko w `Track\EditController` (IncomingTrack nie renamuje wg formatu kolekcji).

## F12. Warstwa Request / walidacja wejścia (`UpdateTrackCommand`) ✅ ZROBIONE (etap 2)

**Problem:** brak walidacji i normalizacji wejścia (w kodzie komentarz autora `// słabe, ogarnąć klasą typu request`). Bezpośredni dostęp do `$postData[...]` (m.in. `$postData['guid']`, `$postData['artist']`) grozi `Undefined array key`, jeśli formularz przyjdzie niekompletny.

**Rozwiązanie:** DTO z fabryką `fromRequest()` robiące w jednym miejscu `trim`, rzutowanie typów, normalizację (NFC), walidację (rok 1900–2100, bpm 1–300, niepusty artist+title), domyślne wartości:

```php
final readonly class UpdateTrackCommand
{
    public function __construct(
        public string $guid,
        public string $artist,
        public string $title,
        public ?string $album,
        public ?int $trackNumber,
        public ?string $publisher,
        public ?string $genre,
        public ?int $year,
        public ?string $initialKey,
        public ?float $bpm,
        public bool $calculateAudioData,
    ) {}
    public static function fromRequest(ServerRequest $r): self { /* trim, cast, validate */ }
}
```

Eliminuje duplikację z `IncomingTrack/EditController::save()`.

## F13. Sprzątanie katalogów sprawdza lokalizację źródła **po** jego przeniesieniu — cleanup jest martwy

**Problem:** w `move()` warunek uruchamiający wyliczenie i usunięcie pustych katalogów wykonywany jest **po** `rename()`:

```php
// plik już przeniesiony ze $source do $target
if (rename($source->getPathname(), $target->getPathname()) === false) { ... }
...
// sprawdzamy lokalizację ŹRÓDŁA, którego już nie ma na dysku
if ($isSingle && $this->trackService->getLocationArbiter()->isInCollection($source)) {
    $this->leftoverPaths = $this->calculateLeftoverPaths($source);
    foreach ($this->leftoverPaths as $leftoverPath) { rmdir($leftoverPath); }
}
```

`TrackLocationArbiter::getPathname()` ma na wejściu `if (!$file->isReadable()) return null;`. Po `rename()` ścieżka `$source` już **nie istnieje** → `isReadable()` = `false` → `getLocation()` = `null` → `isInCollection($source)` = **`false`**. W efekcie blok cleanupu **praktycznie nigdy się nie wykonuje**, a `getLeftoverPaths()` w kontrolerze zwraca pustą tablicę.

**Konsekwencja:** puste katalogi po zmianie artysty/albumu **nigdy nie są sprzątane** i akumulują się w kolekcji. To zmienia framing F7/B2: to nie jest „konflikt dwóch mechanizmów", tylko „mechanizm nie działa wcale".

**Rozwiązanie:** lokalizację/typ tracka i **kandydatów na leftovery wyliczać ze źródła zanim go przeniesiemy** (spójne z F2/F3 — najpierw plan, potem efekty), a dopiero po udanym rename wykonać usunięcie. Po przejściu na `RenameResult` (F5) i `TrackUpdateService` (F2) i tak przechwytujemy stan źródła przed operacją FS, więc naprawa jest naturalną częścią tamtej zmiany.

## F14. `TrackLocationArbiter` błędnie rozpoznaje „w kolekcji" — sprawdza `root_dir`, nie `indexed_dirs`

**Problem (potwierdzony komentarzem w kodzie):** `getLocation()` uznaje plik za „w kolekcji", gdy jego ścieżka zaczyna się od `collection.root_dir` (`/collection`), zamiast od któregoś z faktycznie indeksowanych katalogów (`collection.indexed_dirs`: `/collection/Singles`, `/collection/Other`):

```php
if (str_starts_with($pathname, $this->config->get('collection.root_dir'))) {
    // tutaj powinno się sprawdzić (dodatkowo albo tylko) collection.indexed_dirs, bo teraz
    // źle zakłada, że V testy są w kolekcji
    return self::LOCATION_IN_COLLECTION;
}
```

Skutki:
- Cokolwiek pod `/collection` (np. katalogi testowe, `_new/_zrobione`) jest traktowane jak część kolekcji.
- To arbiter stoi za decyzją cleanupu (F13) i ma być docelowo źródłem `LocationKind`/formatu (F6) oraz granicy pętli sprzątającej (B2). Dopóki nie odróżnia indexed dirs, nie da się na nim oprzeć B2 („granica = indexed_dir") ani F6 rzetelnie.

**Rozwiązanie:** rozpoznawać lokalizację po `collection.indexed_dirs` (i osobno `incoming_dir`), a `root_dir` traktować jako fallback/„unsupported". Z tego samego miejsca wyprowadzić `LocationKind`, format nazwy (F6), literę katalogu dla Singles (B4) oraz granicę sprzątania (B2).

# Część II — błędy logiczne i runtime

## B1. `BackgroundProcess` na ścieżce sprzed rename

**Problem:** proces tła jest uruchamiany **przed** blokiem rename:

```php
if (isset($postData['task:calculate-audio-data'])) {
    $command = sprintf(
        'php /data/bin/console.php track:calculate-audio-data -w "%s"',
        $track->getFile()->getPathname()
    );
    (new BackgroundProcess($command))->run();
}
```

Jeśli użytkownik zaznaczył „Oblicz tonację i BPM" i jednocześnie zmienił metadane wpływające na nazwę, plik zostanie przeniesiony, a proces zadziała na starej, nieistniejącej ścieżce.

**Rozwiązanie:** uruchamiać proces **po** ewentualnym rename, na finalnym `$trackPathname`.

## B2. Niepełne sprzątanie katalogów — rodzic zostaje / hardcoded 2 poziomy

**Problem:** `array_slice($breadcrumbs, -2, 2)` daje `[parent, child]`, a filtr `isPathEmpty` wykonywany jest **przed** `rmdir(child)`. Po usunięciu `Album/` rodzic `Artist/` bywa już pusty, ale nikt go nie usuwa (nie trafił na listę). Zakłada też sztywno strukturę `Artist/Album/file.mp3`.

```php
$breadcrumbs = array_slice($breadcrumbs, -2, 2);
$paths = array_map(fn (Breadcrumb $b) => $b->pathname, $breadcrumbs);
$paths = array_filter($paths, fn (string $p) => $isPathEmpty($p));
```

**Rozwiązanie:** iść w górę od katalogu źródłowego, usuwając dopóki katalog jest pusty i nie wyżej niż **granica** (pętla dynamiczna, nie hardcoded 2 poziomy):

```php
private function calculateLeftoverPaths(SplFileInfo $file, string $boundary): array
{
    $paths = [];
    $dir = $file->getPath();
    while ($dir !== $boundary && $this->isDirectoryEmpty($dir)) {
        $paths[] = $dir;
        $dir = dirname($dir);
    }
    return $paths;
}
```

**Uwaga na granicę (błąd logiczny do uniknięcia):** granicą **nie** powinien być `collection.root_dir` (`/collection`), tylko właściwy **katalog indeksowany** dla danego tracka (`collection.indexed_dirs`: `/collection/Singles` albo `/collection/Other`). Inaczej pętla może usunąć katalog literowy `/collection/Singles/A`, a nawet wspiąć się do `/collection/Singles`, gdy przypadkiem opustoszeje. Granicę wyprowadzić z arbitra/`LocationKind` (F6, F14). Dla struktury Singles (`/collection/Singles/<litera>/<Artist>/<Album>/`) zwykle chcemy zatrzymać się na `<litera>` włącznie, nie wyżej.

## B3. Regex nie łapie placeholderów z podkreśleniem

**Problem:** `/%[a-z]+%/` nie matchuje `%track_number%` ani `%initial_key%`:

```php
if (str_contains($target, '%')) {
    preg_match_all('/%[a-z]+%/', $target, $matches);
    $message = sprintf('Cannot prepare target filename: some metadata fields are empty (%s)',
        implode(', ', $matches[0]));
    throw new \RuntimeException($message);
}
```

Warunek `str_contains` wykryje problem, ale komunikat będzie pusty (`$matches[0]` bez tych pól).

**Rozwiązanie:** `/%[a-z_]+%/`.

## B4. Litera katalogu (`/Singles/A/`) nie aktualizowana przy zmianie artysty

**Problem:** dla `/collection/Singles/A/Artist/Album/file.mp3` `dirname($source->getPath(), 2)` daje `/collection/Singles/A`. Po zmianie artysty z „Artist" na „Bart" plik trafi do `/collection/Singles/A/Bart/...` zamiast `/collection/Singles/B/Bart/...`.

```php
if ($isSingle) {
    $baseDir = dirname($source->getPath(), 2);
} else {
    $baseDir = $source->getPath();
}
$target = sprintf('%s/%s', $baseDir, $target);
```

**Rozwiązanie:** jeśli katalog literowy ma odpowiadać pierwszej literze artysty — wyliczać literę z nowego artysty (część logiki `LocationKind`/`TrackLocationArbiter` z F6/F14). `dirname(..., 2)` do wyprowadzenia do testowalnej metody.

## B5. `scandir()`/`isPathEmpty` — `TypeError` i „śmieciowe" pliki

**Problem:**

```php
$isPathEmpty = fn (string $path): bool => count(scandir($path)) == 2; // . i ..
```

- `scandir()` zwraca `false` przy braku ścieżki/uprawnieniach → `count(false)` rzuca `TypeError` (PHP 8+).
- `.DS_Store`, `Thumbs.db`, `@eaDir`, dotfile-y sprawiają, że „pusty" katalog nie jest sprzątany.
- Race condition: między sprawdzeniem a `rmdir` inny proces może dodać plik (realne przy background procesach).

**Rozwiązanie:**

```php
$isPathEmpty = static function (string $path): bool {
    if (!is_dir($path)) return false;
    $entries = @scandir($path);
    if ($entries === false) return false;
    $entries = array_diff($entries, ['.', '..']);
    $entries = array_filter($entries, static fn ($n) => !str_starts_with($n, '.'));
    return $entries === [];
};
```

Ewentualne „junk" pliki usuwać jawnie z białej listy przed `rmdir`. `rmdir` na niepustym katalogu i tak zwróci `false` — sprawdzać wynik (F7).

## B6. `empty()` zjada legalne wartości (`"0"`) ✅ ZROBIONE (etap 2)

**Problem:**

```php
foreach ($metadata as $name => $value) {
    if (empty($value)) { unset($metadata[$name]); }
}
```

Usuwa pole także dla `"0"` (np. `track_number = "0"`, `year = "0"`, `bpm = "0"`). Wartość `"   "` przechodzi dalej i ląduje w nazwie pliku.

**Rozwiązanie:** świadome typów + `trim`:

```php
if ($value === null || trim((string) $value) === '') { unset($metadata[$name]); }
```

## B7. Sygnatury w `Track` deklarują `string`/`float`, a pola są nullable ✅ ZROBIONE (etap 1)

**Problem:** `TypeError` dla utworów bez bpm/klucza/gatunku:

```php
public function getBpm(): float        // ale $bpm jest ?float
public function getInitialKey(): string // ale $initialKey jest ?string
public function getGenre(): string     // ale $genre jest ?string
```

**Rozwiązanie:** poprawić sygnatury na `?float` / `?string` (dotyczy też setterów `withBpm(float)`, `withInitialKey(string)`, `withGenre(string)`, które również deklarują non-nullable).

**Uwaga o priorytecie:** to **nie** jest tylko „bezpieczna kosmetyka na start" — to **żywy crash na ścieżce zapisu**. W `Track\EditController::save()` warunki zachowujące dane wołają `$track->getInitialKey()` i `$track->getBpm()`; dla utworu bez tonacji/BPM (a takie istnieją — patrz `@fixme` w `Track.php`) getter zwraca `null` z metody typowanej `: string`/`: float` → **`TypeError` i błąd 500 przy każdej próbie zapisu**. Dlatego słusznie jest w kroku 1, ale opis powinien oddawać, że to realny bug produkcyjny, nie profilaktyka. **Dobra wiadomość:** `Model/IncomingTrack.php` ma te gettery już poprawnie nullable (`getBpm(): ?float`, `getInitialKey(): ?string`, `getGenre(): ?string`), więc B7 dotyczy **wyłącznie `Track.php`** — nie ruszać IncomingTrack.

> **⚠️ Do decyzji (obserwacja z etapu 1):** poprawa dotknęła wyłącznie `Track.php`, ale `TrackDto` (`Model/TrackDto.php`) nadal deklaruje non-nullable `float $bpm`, `string $genre`, `string $initialKey`. Ścieżka zapisu `trackService->save()` → `TrackDto::fromModel()` może więc dla utworu bez BPM/tonacji/gatunku nadal rzucić `TypeError` — B7 na samym `Track.php` przenosi crash z gettera w kontrolerze do konstruktora DTO, nie eliminuje go na ścieżce zapisu. Do rozważenia rozszerzenie na `TrackDto` (poza pierwotnym zakresem B7).

## B8. `var_dump` + `exit` zamiast obsługi błędów

**Problem:** (w obu kontrolerach):

```php
} catch (\Exception $e) {
    var_dump($e->getMessage());
    var_dump($this->id3Adapter->getWriterErrors());
    var_dump($this->id3Adapter->getWriterWarnings());
    exit;
}
```

Pusta biała strona, brak logu do Monologa, zablokowana przeglądarka.

**Rozwiązanie:** log + flash message + PRG redirect do `edit` z komunikatem błędu (albo `throw` i centralny handler Slima). Warningi z `getWriterWarnings()` można pokazać jako flash-warning:

```php
try {
    $result = $this->trackUpdateService->update($track, $command);
} catch (TrackUpdateException $e) {
    $this->logger->error('Track update failed', ['error' => $e->getMessage()]);
    $this->flash->addError($e->getMessage());
    return $response->withRedirect($editUrl);
}
$this->flash->addSuccess('Zapisano.');
return $response->withRedirect($result->trackUrl);
```

## B9. Uboga sanityzacja nazwy pliku ✅ ZROBIONE (etap 3)

**Problem:**

```php
public static function sanitizeForFilesystem(string $value): string
{
    $value = str_replace(['/', ':'], '-', $value);
    $value = str_replace('"', "'", $value);
    return str_replace(['*', '?'], '', $value);
}
```

Brakuje: znaków sterujących (`\x00-\x1F`, `\x7F`), `\ < > |`, ucięcia wiodących/końcowych spacji i kropek (Windows), nazw zarezerwowanych (`CON`, `PRN`, `AUX`, `NUL`, `COM1..9`, `LPT1..9`), limitu długości (255 na komponent), normalizacji Unicode (NFC — istotne na APFS), obsługi przypadku gdy po sanityzacji wartość jest pusta, kolapsu wielokrotnych spacji. Realne wejścia: `AC/DC`, `100%`, `P:\stuff`.

**Rozwiązanie:** rozszerzyć `sanitizeForFilesystem()` o powyższe reguły; przy pustym wyniku rzucić błąd lub podstawić wartość zastępczą. Uwaga: sanityzacja jest też punktem zaczepienia dla F1 — po niej porównujemy `desired` vs `current`. Nie zastępuje jednak `escapeshellarg()` z F8/B11 — sanityzacja nazwy pliku i escaping argumentu powłoki to dwie różne warstwy.

## B10. `mkdir(..., 0777, true)` — zbyt liberalne uprawnienia ✅ ZROBIONE (etap 3)

**Problem:** `0777` ignoruje umask projektu.

**Rozwiązanie:** `0775` (lub konfigurowalne).

## B11. Command injection przez `shell_exec` na ścieżkach z metadanych ⭐ nowe

**Problem (bezpieczeństwo):** kontroler składa polecenia powłoki, wstawiając w nie ścieżki wyprowadzone z metadanych podanych przez użytkownika (artist/title/album → nazwa pliku → `leftoverPath`/`createdPath`/`trackPathname`), otoczone jedynie ręcznym cudzysłowem:

```php
$command = sprintf('php %s/bin/console.php collection:clean "%s"', $this->config->get('base_dir'), $leftoverPath);
shell_exec($command);
```

Sanityzacja nazwy (B9) usuwa `/ : " * ?`, ale **nie** usuwa `` ` ``, `$`, `(`, `)`, `;`. W obrębie cudzysłowów w bashu konstrukcje `$(...)` oraz backticki **nadal są wykonywane**. Wystarczy np. `title = "$(reboot)"` — przejdzie sanityzację (brak zakazanych znaków), trafi do nazwy pliku, a stamtąd do `shell_exec` i wykona się jako polecenie. To realna podatność wykonania dowolnego kodu, nawet przy założeniu „ufam użytkownikowi" (bo obniża próg z pomyłki do katastrofy i otwiera wektor przez np. tagi zaimportowane z zewnętrznego źródła).

**Rozwiązanie:** to samo co w F8 — najlepiej wywoływać komendy jako serwisy PHP z DI (bez powłoki), a jeśli już przez CLI, to bezwzględnie `escapeshellarg()` na każdym argumencie (i `escapeshellcmd`/tablicowa forma `proc_open`, nie interpolacja stringa). Traktować wspólnie z F8.

## B12. Nie da się wyczyścić pola metadanych (puste wejście nie nadpisuje istniejącego tagu) ⭐ nowe ✅ ZROBIONE (etap 2)

> **Decyzja (etap 2):** przyjęto semantykę **puste = usuń tag**. Zamknięta w `UpdateTrackCommand::toMetadata()` — puste pola przekazywane są jako pusty string, a `Id3Adapter::writeMetadata` zapisuje cały tag id3v2 na nowo, więc pusty string czyści dany tag. Usunięto logikę "zapobiega usunięciu danych" dla `initial_key`/`bpm` z obu kontrolerów. **Uwaga:** faktyczny efekt na dysku (czy getID3 zapisuje pustą ramkę vs. ją pomija) weryfikowalny dopiero w runtime Docker/getID3.

**Problem:** kombinacja dwóch rzeczy sprawia, że użytkownik **nie może usunąć** wartości pola (album, wydawca, gatunek, rok, tonacja, bpm):
1. `foreach ($metadata ...) if (empty($value)) unset($metadata[$name]);` — puste pola są wyrzucane z tablicy, więc nie trafiają do `writeMetadata()`.
2. `Id3Adapter::writeMetadata()` przy `remove_other_tags = false` (domyślnie; opcja „remove-other-tags" w formularzu jest zakomentowana) zapisuje **tylko** przekazane tagi i nie kasuje pozostałych.

Efekt: wyczyszczenie pola w formularzu i zapis → stary tag zostaje w pliku bez zmian. Dla `initial_key` i `bpm` jest to częściowo zamierzone (jawny kod „zapobiega usunięciu danych w przypadku braku ich podania"), ale dla albumu/wydawcy/gatunku/roku to nieoczekiwane — użytkownik sądzi, że skasował wartość.

**Rozwiązanie:** zdecydować świadomie o semantyce „puste pole": albo puste = „nie zmieniaj" (jak dziś, ale wtedy udokumentować i dać osobny sposób kasowania), albo puste = „usuń tag" (wymaga włączenia `remove_other_tags` lub jawnego zapisania pustej wartości). Decyzję zamknąć w `UpdateTrackCommand` (F12) — jedno miejsce, świadome typów i wartości pustych (spójne z B6).

# Część III — kosmetyka / drobiazgi

- **`array_merge` na tablicy asocjacyjnej** w `rename()` nie reindeksuje — nic nie robi, usunąć: `array_merge(array_filter($metadata, fn ($f) => trim($f)))`.
- **`trim($field)` rzuci `TypeError`**, gdy pole jest `null`/`int` (np. `track_number`, `year` z DB) — rzutować do stringa / filtrować typami.
- **`if ($metadata['track_number'] < 10)`** — porównanie stringa z intem; użyć `(int) $metadata['track_number'] < 10` i `str_pad` do formatowania (`01`, `02`...).
- **Format jako stała/config**, nie literał w kontrolerze (spójne z F6).
- **`dirname($source->getPath(), 2)`** — „magia"; wyprowadzić do `LocationKind::baseDirFor()` z testem jednostkowym (spójne z B4/F6).
- **`getNotFoundRedirect`** — sensowny, zostawić.

# Sugerowana kolejność wdrożenia

1. ✅ **ZROBIONE** — **F1** (rename przez plik tymczasowy) + **F9** (rollback) + **B7** (nullable getters) — bezpieczne, punktowe, zdejmują największe ryzyko utraty danych.
2. ✅ **ZROBIONE (bez B8)** — **F12** (`UpdateTrackCommand`) + **B6** (`empty`/`0`) + **B12** (semantyka pustego pola: **puste = usuń tag**). **B8** (koniec z `var_dump`/`exit`) celowo przesunięte na sam koniec refactoru (do zrobienia).
3. ✅ **ZROBIONE** — **F5** (`RenameResult`) + **F4** (metadane z command) + **B9** (sanityzacja) + **B10** (uprawnienia).
4. ✅ **ZROBIONE** — **F2** (`TrackUpdateService` koordynujący) + **F3** (dry-run + weryfikacja) + **F10** (jeden zapis DB). _Uwaga: kontroler nadal decyduje o formacie (przekazywany do `update()`) — przeniesienie do arbitra to etap 5 (F6). `calculate-audio-data` liczy się teraz na finalnej ścieżce (efekt uboczny sprzyjający B1)._
5. **F6** + **F14** (`LocationKind`/Arbiter poprawnie po `indexed_dirs`, format deklaratywny) + **B4** (litera katalogu) + **B2** (dynamiczne sprzątanie, granica = indexed_dir) + **B5** (`isPathEmpty`) + **F13** (wyliczanie leftoverów ze źródła PRZED rename — inaczej cleanup pozostaje martwy).
6. **F7** + **F8** + **B11** (jednolity, asynchroniczny cleanup/reindex bez command injection) + **B1** (`calculate-audio-data` po renamie) + **F11** (wspólny `TrackMetadataWriter`). **Uwaga:** F8 (async reindex) jest sprzężony z **F10** (GUID/URL liczone w procesie) i **F2** (`UpdateResult`) — reindexu nie da się zasynchronizować bez ich jednoczesnego wdrożenia, bo zepsuje redirect (patrz F8 ⚠️).
7. **Część III** — kosmetyka przy okazji dotykanych plików.
