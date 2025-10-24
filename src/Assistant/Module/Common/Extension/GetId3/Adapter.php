<?php

namespace Assistant\Module\Common\Extension\GetId3;

use Assistant\Module\Common\Extension\GetId3\Adapter\Id3ReaderOptions;
use Assistant\Module\Common\Extension\GetId3\Adapter\Id3WriterOptions;
use Assistant\Module\Common\Extension\GetId3\Adapter\Metadata\Id3v2;
use Assistant\Module\Common\Extension\GetId3\Adapter\MetadataAdapterInterface;
use Assistant\Module\Common\Extension\GetId3\Exception\ReadException;
use Assistant\Module\Common\Extension\GetId3\Exception\WriteException;
use getID3;
use getid3_writetags;
use SplFileInfo;
use Throwable;

final class Adapter
{
    private SplFileInfo $file;

    private getID3 $id3Reader;
    private getid3_writetags $id3Writer;

    private array $metadata = [];
    private ?int $trackDuration = null;

    public function __construct(private MetadataAdapterInterface $metadataAdapter = new Id3v2())
    {
        $this->id3Reader = new getID3();
        $this->setId3ReaderOptions(Id3ReaderOptions::defaults());

        $this->id3Writer = new getid3_writetags();
        $this->setId3WriterOptions(Id3WriterOptions::defaults());
    }

    /** Analizuje plik (utwór muzyczny) i odczytuje zawarte w nim metadane */
    public function setFile(SplFileInfo $file): self
    {
        $this->reset();

        $this->file = $file;

        return $this;
    }

    public function setId3ReaderOptions(Id3ReaderOptions $id3ReaderOptions): self
    {
        $this->id3Reader->setOption($id3ReaderOptions->toArray());

        return $this;
    }

    public function setId3WriterOptions(Id3WriterOptions $id3WriterOptions): self
    {
        /**
         * To jest zakomentowane, ponieważ ustawienie $id3WriterOptions->overwriteTags na false rzuca wyjątek:
         * "$id3Writer->overwrite_tags=false is known to be buggy in this version of getID3.
         * Check http://github.com/JamesHeinrich/getID3 for a newer version."
         *
         * @see https://github.com/JamesHeinrich/getID3/blob/master/getid3/write.php#L505
         */
        // $this->id3Writer->overwrite_tags = $id3WriterOptions->overwriteTags;

        $this->id3Writer->remove_other_tags = $id3WriterOptions->removeOtherTags;
        $this->id3Writer->tag_encoding = $id3WriterOptions->encoding;
        $this->id3Writer->tagformats = $id3WriterOptions->tagFormats;

        return $this;
    }

    /** Analizuje dane zawarte w pliku (utworze muzycznym) */
    public function analyze(): self
    {
        $this->reset();

        try {
            $rawInfo = $this->id3Reader->analyze($this->file->getPathname());
        } catch (Throwable $e) {
            $message = sprintf('Unable to read metadata from "%s": %s', $this->file->getPathname(), $e->getMessage());

            throw new ReadException($message, previous: $e);
        }

        $this->metadata = $this->metadataAdapter->setRawInfo($rawInfo)->getMetadata();
        $this->trackDuration = isset($rawInfo['playtime_seconds'])
            ? (int) $rawInfo['playtime_seconds']
            : null;

        return $this;
    }

    /** Zwraca metadane odczytane z pliku (utworu muzycznego) */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /** Zwraca długość utworu muzycznego w sekundach */
    public function getTrackDuration(): ?int
    {
        return $this->trackDuration;
    }

    /**
     * Zapisuje podane metadane w pliku (utworze muzycznym)
     *
     * Uwaga, ta metoda działa w ten sposób, że zapisuje tylko te pola, które zostaną przekazane.
     * Oznacza to, że jeśli aplikacja przekaże tutaj tylko klucz i tonację, to pozostałe pola (np. artysta, tytuł, rok)
     * zostaną usunięte z pliku. Zob. komentarz w setId3ReaderOptions.
     *
     * @idea: Zastanowić się nad zabezpieczeniem powyższego albo wprowadzić flagę jawnie kontrolującą ww. zachowanie
     */
    public function writeMetadata(array $metadata): bool
    {
        $fileModificationTime = $this->file->getMTime();

        $this->id3Writer->filename = $this->file->getPathname();

        if ($this->id3Writer->remove_other_tags) {
            $this->id3Writer->DeleteTags(Id3WriterOptions::TAGS_TO_REMOVE);

            $this->id3Writer->errors = []; // ignorujemy błędy mówiące o tym, że jakiegoś taga nie ma
        }

        $this->id3Writer->tag_data = $this->metadataAdapter->setRawInfo([])->prepareMetadata($metadata);

        $result = $this->id3Writer->WriteTags();

        $this->id3Reader->analyze($this->file->getPathname());

        touch($this->file->getPathname(), $fileModificationTime);

        if ($result === false) {
            throw new WriteException(sprintf('Unable to save metadata to "%s"', $this->file->getPathname()));
        }

        return true;
    }

    /** Zwraca listę niekrytycznych błędów wykrytych podczas zapisywania metadanych w utworze muzycznym */
    public function getWriterWarnings(): array
    {
        return $this->id3Writer->warnings;
    }

    /** Zwraca listę krytycznych błędów wykrytych podczas zapisywania metadanych w utworze muzycznym */
    public function getWriterErrors(): array
    {
        $errors = [];

        foreach ($this->id3Writer->errors as $error) {
            $result = htmlspecialchars_decode($error);
            $matches = [];

            if ((bool) preg_match_all('/<li>(.*?)<\/li>/i', $result, $matches) === true) {
                if (($pos = strpos($result, ':')) !== false) {
                    $result = substr($result, 0, $pos + 1) . ' ';
                }

                $result .= implode('; ', $matches[1]);
            }

            $errors[] = strip_tags($result);

            unset($error, $result, $matches);
        }

        return $errors;
    }

    private function reset(): void
    {
        $this->metadata = [];
        $this->trackDuration = null;

        $this->id3Writer->warnings = [];
        $this->id3Writer->errors = [];
    }
}
