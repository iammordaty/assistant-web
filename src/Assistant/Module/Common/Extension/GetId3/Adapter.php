<?php

namespace Assistant\Module\Common\Extension\GetId3;

use Assistant\Module\Common\Extension\GetId3\Adapter\Metadata\Id3v2;
use Assistant\Module\Common\Extension\GetId3\Exception\ReadException;
use Assistant\Module\Common\Extension\GetId3\Exception\WriteException;
use getID3;
use getid3_write_id3v2;
use getid3_writetags;
use SplFileInfo;

// TODO: Zobaczyć dlaczego nie działa usuwanie tagów APE
// TODO: Poprawić settery ustawiania opcji

final class Adapter
{
    private array $id3ReaderOptions = [
        'encoding' => 'UTF-8',
        'option_tag_id3v1' => false,
        'option_tag_id3v2' => true,
        'option_tag_lyrics3' => false,
        'option_tag_apetag' => false,
        'option_tags_process' => true,
        'option_tags_html' => false,
        'option_extra_info' => false,
        'option_save_attachments' => false,
        'option_md5_data' => false,
    ];

    private array $id3WriterOptions = [
        'tag_encoding' => 'UTF-8',
        'tagformats' => [ 'id3v2.3' ],
        'remove_other_tags' => false,
    ];

    private getID3 $id3Reader;

    private getid3_writetags $id3Writer;

    private SplFileInfo $file;

    private array $rawInfo = [];

    // @todo: możliwe że jest inne podzielenie parametrów (poprzez settery, a może nową klasę z parametrami),
    //        bo obecnie korzystanie z niniejszej klasy jest niewygodne
    public function __construct(
        SplFileInfo $file = null,
        ?array $id3ReaderOptions = null,
        ?array $id3WriterOptions = null,
    ) {
        $this->id3Reader = new getID3();

        $this->id3Writer = new getid3_writetags();
        $this->id3Writer->tag_encoding = $this->id3WriterOptions['tag_encoding'];
        $this->id3Writer->tagformats = $this->id3WriterOptions['tagformats'];
        $this->id3Writer->remove_other_tags = $this->id3WriterOptions['remove_other_tags'];

        if ($file !== null) {
            $this->setFile($file);
        }

        if ($id3ReaderOptions !== null) {
            $this->setId3ReaderOptions($id3ReaderOptions);
        }

        if ($id3WriterOptions !== null) {
            $this->setId3WriterOptions($id3WriterOptions);
        }
    }

    /**
     * Analizuje plik (utwór muzyczny) i odczytuje zawarte w nim metadane
     *
     * @deprecated Problematyczne, nie używać. Docelowo do usunięcia.
     *
     * @param SplFileInfo $file
     * @return self
     */
    public function setFile(SplFileInfo $file): self
    {
        $this->rawInfo = [ ];
        $this->file = $file;

        return $this;
    }

    public function setId3ReaderOptions(array $id3ReaderOptions): self
    {
        $this->id3ReaderOptions = $id3ReaderOptions;

        $this->id3Reader->setOption($this->id3ReaderOptions);

        return $this;
    }

    public function setId3WriterOptions(array $id3WriterOptions): self
    {
        $this->id3WriterOptions = $id3WriterOptions;

        $this->id3Writer->tag_encoding = $this->id3WriterOptions['tag_encoding'];
        $this->id3Writer->tagformats = $this->id3WriterOptions['tagformats'];
        $this->id3Writer->remove_other_tags = $this->id3WriterOptions['remove_other_tags'];

        return $this;
    }

    /**
     * Zwraca metadane zawarte w pliku (utworze muzycznym)
     *
     * @return array
     *
     * @throws ReadException
     */
    public function readId3v2Metadata(): array
    {
        try {
            $this->rawInfo = $this->id3Reader->analyze($this->file->getPathname());
        } catch (\Throwable $e) {
            $message = sprintf('Unable to read metadata from "%s": %s', $this->file->getPathname(), $e->getMessage());

            throw new ReadException($message);
        }

        return (new Id3v2($this->rawInfo))->getMetadata();
    }

    /** Zwraca długość utworu muzycznego w sekundach */
    public function getTrackLength(): ?int
    {
        return isset($this->rawInfo['playtime_seconds']) ? (int) $this->rawInfo['playtime_seconds'] : null;
    }

    /**
     * Zapisuje podane metadane w pliku (utworze muzycznym)
     *
     * @todo $mode = 'overwrite' / 'append', lub - najlepiej - dwie osobne metody publiczne
     *
     * @param array $metadata
     * @param bool $overwrite
     * @return bool
     *
     * @throws WriteException
     */
    public function writeId3v2Metadata(array $metadata, bool $overwrite = false): bool
    {
        $this->id3Writer->warnings = [];
        $this->id3Writer->errors = [];

        $fileModificationTime = $this->file->getMTime();

        if ($overwrite) {
            $this->rawInfo = [];

            $id3v2Writer = new getid3_write_id3v2();
            $id3v2Writer->filename = $this->file->getPathname();
            $id3v2Writer->RemoveID3v2();

            unset($id3v2Writer);
        }

        if (empty($this->rawInfo)) {
            $this->rawInfo = $this->id3Reader->analyze($this->file->getPathname());
        }

        $this->id3Writer->filename = $this->file->getPathname();
        $this->id3Writer->tag_data = (new Id3v2($this->rawInfo))->prepareMetadata($metadata);

        $result = $this->id3Writer->WriteTags();

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
}
