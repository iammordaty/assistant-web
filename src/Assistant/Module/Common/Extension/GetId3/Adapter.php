<?php

namespace Assistant\Module\Common\Extension\GetId3;

use Assistant\Module\File;

use \getID3;
use \getid3_writetags;

class Adapter
{
    /**
     * @var array
     */
    private $id3ReaderOptions = [
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

    /**
     * @var array
     */
    private $id3WriterOptions = [
        'tag_encoding' => 'UTF-8',
        'tagformats' => [ 'id3v2.3' ],
    ];

    /**
     * @var getID3
     */
    protected $id3Reader;

    /**
     * @var getid3_writetags
     */
    protected $id3Writer;

    /**
     * @var File\Extension\SplFileInfo $file
     */
    protected $file;

    /**
     * @var array
     */
    protected $rawInfo;

    /**
     * Konstruktor
     */
    public function __construct(File\Extension\SplFileInfo $file = null)
    {
        $this->id3Reader = new getID3();
        $this->id3Reader->setOption($this->id3ReaderOptions);

        $this->id3Writer = new getid3_writetags();
        $this->id3Writer->tag_encoding = $this->id3WriterOptions['tag_encoding'];
        $this->id3Writer->tagformats = $this->id3WriterOptions['tagformats'];

        if ($file !== null) {
            $this->setFile($file);
        }
    }

    /**
     * Analizuje plik (utwór muzyczny) i odczytuje zawarte w nim metadane
     *
     * @param File\Extension\SplFileInfo $file
     * @return self
     */
    public function setFile(File\Extension\SplFileInfo $file)
    {
        $this->rawInfo = [ ];
        $this->file = $file;

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

        return (new Adapter\Metadata\Id3v2($this->rawInfo))->getMetadata();
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
     *
     * @throws Exception\Writer
     * @return bool
     */
    public function writeId3v2Metadata(array $metadata)
    {
        if (empty($this->rawInfo)) {
            $this->rawInfo = $this->id3Reader->analyze($this->file->getPathname());
        }

        $fileModificationTime = $this->file->getMTime();

        $this->id3Writer->filename = $this->file->getPathname();
        $this->id3Writer->tag_data = (new Adapter\Metadata\Id3v2($this->rawInfo))->prepareMetadata($metadata);

        $result = $this->id3Writer->WriteTags();

        touch($this->file->getPathname(), $fileModificationTime);

        if ($result === false) {
            throw new Exception\WriterException(
                sprintf(
                    'Cannot write tags into "%s" (error: %s, warnings: %s)',
                    $node->getPathname(),
                    implode('; ', $this->id3Writer->errors) ?: 'none',
                    implode('; ', $this->id3Writer->warnings) ?: 'none'
                )
            );
        }

        return $result;
    }
}
