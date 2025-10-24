<?php

namespace Assistant\Module\Common\Extension\GetId3\Adapter;

final readonly class Id3ReaderOptions
{
    public function __construct(
        public string $encoding = 'UTF-8',
        public bool $processTags = true,
        public bool $id3v1 = false,
        public bool $id3v2 = true,
        public bool $lyrics3 = false,
        public bool $apeTag = false,
        public bool $htmlTags = false,
        public bool $extraInfo = false,
        public bool $saveAttachments = false,
        public bool $md5Data = false,
    ) {
    }

    public static function defaults(): self
    {
        return new self();
    }

    public function toArray(): array
    {
        return [
            'encoding' => $this->encoding,
            'option_tag_id3v1' => $this->id3v1,
            'option_tag_id3v2' => $this->id3v2,
            'option_tag_lyrics3' => $this->lyrics3,
            'option_tag_apetag' => $this->apeTag,
            'option_tags_process' => $this->processTags,
            'option_tags_html' => $this->htmlTags,
            'option_extra_info' => $this->extraInfo,
            'option_save_attachments' => $this->saveAttachments,
            'option_md5_data' => $this->md5Data,
        ];
    }
}
