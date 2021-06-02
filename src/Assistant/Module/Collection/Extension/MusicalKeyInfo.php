<?php

namespace Assistant\Module\Collection\Extension;

use KeyTools\KeyTools;

final class MusicalKeyInfo
{
    public function __construct(private KeyTools $keyTools)
    {
    }

    public static function factory(?string $notation = KeyTools::NOTATION_CAMELOT_KEY): self
    {
        $keyTools = KeyTools::fromNotation($notation);

        return new self($keyTools);
    }

    /**
     * Zwraca informacje o podanym kluczu muzycznym
     *
     * @link http://blog.dubspot.com/harmonic-mixing-w-dj-endo-part-1/
     *
     * @param string $musialKey
     * @return array|null
     */
    public function get(string $musialKey): ?array
    {
        if (!$this->keyTools->isValidKey($musialKey)) {
            return null;
        }

        $join = static fn($lines) => implode(' ', $lines);

        return [
            'key' => $musialKey,
            'title' => sprintf(
                "%s (%s)",
                $musialKey,
                $this->keyTools->convertKeyToNotation($musialKey, KeyTools::NOTATION_MUSICAL_ESSENTIA)
            ),
            'description' => null, // tutaj będzie można przekazać informacje o kluczu: emocje, kolory, itp
            'similarKeys' => [
                [
                    'title' => sprintf('To %s', $this->keyTools->isMinorKey($musialKey) ? 'major' : 'minor'),
                    'value' => $this->keyTools->relativeMinorToMajor($musialKey),
                    'description' => $join([
                        'This combination will likely sound good because the notes of both scales are the same,',
                        'but the root note is different. The energy of the room will change dramatically.',
                    ]),
                ],
                [
                    'title' => 'Perfect fourth',
                    'value' => $this->keyTools->perfectFourth($musialKey),
                    'description' => $join([
                        'I like to say this type of mix will take the crowd deeper.',
                        'It won\'t raise the energy necessarily but will give your listeners goosebumps!',
                    ]),
                ],
                [
                    'title' => 'Perfect fifth',
                    'value' => $this->keyTools->perfectFifth($musialKey),
                    'description' => 'This will raise the energy in the room.',
                ],
                [
                    'title' => 'Minor third',
                    'value' => $this->keyTools->minorThird($musialKey),
                    'description' => $join([
                        'While these scales have 3 notes that are different,',
                        'I\'ve found that they still sound good played together',
                        'and tend to raise the energy of a room.',
                    ]),
                ],
                [
                    'title' => 'Half step',
                    'value' => $this->keyTools->halfStep($musialKey),
                    'description' => $join([
                        'While these two scales have almost no notes in common,',
                        'musically they shouldn’t sound good together but I\'ve found if you plan it right',
                        'and mix a percussive outro of one song with a percussive intro of another song,',
                        'and slowly bring in the melody this can have an amazing effect musically and',
                        'raise the energy of the room dramatically.',
                    ]),
                ],
                [
                    'title' => 'Whole step',
                    'value' => $this->keyTools->wholeStep($musialKey),
                    'description' => $join([
                        'This will raise the energy of the room. I like to call it "hands in the air" mixing,',
                        'and others might call it "Energy Boost mixing".',
                    ]),
                ],
                [
                    'title' => 'Dominant relative',
                    'value' => $this->keyTools->dominantRelative($musialKey),
                    'description' => $join([
                        'I\'ve found this is the best way to go from Major to Minor keys',
                        'and from Minor to Major because the scales only have one note difference',
                        'and the combination sounds great.',
                    ]),
                ],
            ],
        ];
    }
}
