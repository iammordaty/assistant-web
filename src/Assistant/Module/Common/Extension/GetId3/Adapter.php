<?php

namespace Assistant\Module\Common\Extension\GetId3;

use Assistant\Module\Common\Extension\GetId3\Adapter\Metadata\Id3v2;
use Assistant\Module\Common\Extension\GetId3\Exception\WriterException;
use getID3;
use getid3_write_id3v2;
use getid3_writetags;
use SplFileInfo;

// TODO: Zobaczyć dlaczego nie działa usuwanie tagów APE
// TODO: Poprawić settery ustawiania opcji

class Adapter
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

    protected getID3 $id3Reader;

    /**
     * @var getid3_writetags
     */
    protected $id3Writer;

    /**
     * @var SplFileInfo $file
     */
    protected $file;

    /**
     * @var array
     */
    protected $rawInfo;

    public function __construct(SplFileInfo $file = null, ?array $id3ReaderOptions = null, ?array $id3WriterOptions = null)
    {
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
     * @param SplFileInfo $file
     * @return self
     */
    public function setFile(SplFileInfo $file)
    {
        $this->rawInfo = [ ];
        $this->file = $file;

        return $this;
    }

    public function setId3ReaderOptions(array $id3ReaderOptions)
    {
        $this->id3ReaderOptions = $id3ReaderOptions;

        $this->id3Reader->setOption($this->id3ReaderOptions);

        return $this;
    }

    public function setId3WriterOptions(array $id3WriterOptions)
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
     */
    public function readId3v2Metadata()
    {
        $this->rawInfo = $this->id3Reader->analyze($this->file->getPathname());

        return (new Id3v2($this->rawInfo))->getMetadata();
    }

    /**
     * Zwraca długość utworu muzycznego w sekundach
     *
     * @return int|null
     */
    public function getTrackLength()
    {
        return isset($this->rawInfo['playtime_seconds']) ? (int) $this->rawInfo['playtime_seconds'] : null;
    }

    /**
     * Zapisuje podane metadane w pliku (utworze muzycznym)
     * @todo $mode = 'overwrite' / 'append', lub - najlepiej - dwie osobne metody publiczne
     *
     * @param array $metadata
     * @param bool $overwrite
     * @return bool
     * @throws WriterException
     */
    public function writeId3v2Metadata(array $metadata, $overwrite = false): bool
    {
        $this->id3Writer->warnings = [];
        $this->id3Writer->errors = [];

        $fileModificationTime = \DateTime::createFromFormat('U', $this->file->getMTime());

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

        // obejście warninga (podnoszonego do wyjątku) generowanego przez funkcję touch():
        // touch(): Utime failed: Operation not permitted
        exec(sprintf(
            'touch --no-create -d "%s" "%s"',
            $fileModificationTime->format('Y-m-d H:i'),
            $this->file->getPathname()
        ));

        if ($result === false) {
            throw new WriterException(
                sprintf('Unable to save metadata to into a "%s"', $this->file->getPathname())
            );
        }

        return true;
    }

    /**
     * Zwraca listę niekrytycznych błędów wykrytych podczas zapisywania metadanych w utworze muzycznym
     *
     * @return array
     */
    public function getWriterWarnings()
    {
        return $this->id3Writer->warnings;
    }

    /**
     * Zwraca listę krytycznych błędów wykrytych podczas zapisywania metadanych w utworze muzycznym
     *
     * @return array
     */
    public function getWriterErrors()
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
