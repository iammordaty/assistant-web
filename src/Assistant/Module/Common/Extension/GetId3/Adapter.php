<?php

namespace Assistant\Module\Common\Extension\GetId3;

use Assistant\Module\File;

use \getID3;

class Adapter
{
    /**
     * @var array
     */
    private $getId3Options = [
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
     * @var \getID3
     */
    protected $id3;

    /**
     * @var array
     */
    protected $rawInfo;

    /**
     * Konstruktor
     */
    public function __construct()
    {
        $this->id3 = new getID3();
        $this->id3->setOption($this->getId3Options);
    }

    /**
     * Analizuje plik (utwór muzyczny) i odczytuje zawarte w nim metadane
     *
     * @param \Assistant\Module\File\Extension\SplFileInfo $file
     * @return self
     */
    public function analyze(File\Extension\SplFileInfo $file)
    {
        $this->rawInfo = $this->id3->analyze($file->getPathname());

        return $this;
    }

    /**
     * Zwraca metadane zawarte w pliku (utworze muzycznym)
     *
     * @return array
     */
    public function getId3v2Metadata()
    {
        return (new Adapter\Metadata\Id3v2($this->rawInfo))->getMetadata();
    }

    /**
     * Zwraca długość utworu muzycznego w sekundach
     *
     * @return integer
     */
    public function getTrackLength()
    {
        return (int) $this->rawInfo['playtime_seconds'];
    }
}
