<?php

namespace Assistant\Module\Common\Extension;

/**
 * https://github.com/mossspence/trakofflive/blob/master/appsrc/moss/musicapp/finder/keyTools.php
 *
 * @author oneilstuart
 */
class KeyTools
{
    public $keycodes;
    public $camelotCode;
    public $openKeyCode;
    public $keyname;
    public $altkeyname;

    public function __construct()
    {
        $this->camelotCode = self::camelotCodes();
        $this->openKeyCode = self::openKeyCodes();
        $this->keyname = self::keyNames();
        $this->altkeyname = self::altKeyNames();
    }
    /*
     * the important note is this:
     * camelotCode[0] = '1A' == openKeyCode[0] = '6M' == keyNames[0] = 'Abm' == altKeyName[0] = 'G#m'
     * and so on and so on ...
     * and they really should be CONST
     */
    private static function camelotCodes()
    {
        return array('1A', '1B', '2A', '2B', '3A', '3B', '4A', '4B', '5A', '5B',
            '6A', '6B', '7A', '7B', '8A', '8B', '9A', '9B', '10A', '10B', '11A',
            '11B', '12A', '12B', );
    }
    private static function openKeyCodes()
    {
        return array('6M', '6D', '7M', '7D', '8M', '8D', '9M', '9D', '10M', '10D',
            '11M', '11D', '12M', '12D', '1M', '1D', '2M', '2D', '3M', '3D', '4M',
            '4D', '5M', '5D', );
    }
    private static function keyNames()
    {
        return array('Abm', 'B', 'Ebm', 'Gb', 'Bbm', 'Db', 'Fm', 'Ab', 'Cm',
            'Eb', 'Gm', 'Bb', 'Dm', 'F', 'Am', 'C', 'Em', 'G', 'Bm', 'D', 'Gbm',
            'A', 'Dbm', 'E', );
    }
    private static function altKeyNames()
    {
        return array('G#m', 'B', 'D#m', 'F#', 'A#m', 'C#', 'Fm', 'G#', 'Cm',
            'D#', 'Gm', 'A#', 'Dm', 'F', 'Am', 'C', 'Em', 'G', 'Bm', 'D', 'F#m',
            'A', 'C#m', 'E', );
    }
    /**
     * calculateNextKey.
     *
     * @param string $currentKey as a camelot keycode
     *                           camelot keycode looks like so '12A', '06B', '9b', '01a', ...
     * @param int    $steps
     * @param bool   $modeToggle
     *
     * @return string $nextKey
     *
     * Calculate the next camelot key from the key given or returns NULL on bad input
     */
    /**
     * @assert (0, 0) == 0
     * @assert ('00000000000000000000000000000000000000000000006A', 0) == '6A'
     * @assert ('000000000000001A', 1) == '2A'
     * @assert ('004B', 1) == '5B'
     * @assert ('1B', 2) == '3B'
     * @assert ('1A', 1, TRUE) == '2B'
     * @assert ('4B', 11, TRUE) == '3A'
     * @assert ('01B', 7, TRUE) == '8A'
     */
    public function calculateNextKey($currentKey, $steps = 0, $modeToggle = false)
    {
        $base = 12;
        $nextKey = null;
        $key_index = self::getKeyIndex($currentKey);

        // assert we are given a camelotCode and a step smaller than $base
        if (false !== $key_index && ($base >= abs(intval($steps)))) {
            // determine whether key_index should be shifted
            // Major [B] (even) -> Minor [A] (odd)  or vice versa before stepping
            $key_index = ($modeToggle) ? ($key_index % 2) ? ($key_index - 1) : ($key_index + 1) : $key_index;

            $stepChange = (0 < intval($steps)) ? intval($steps * 2) : (($base * 2) + intval($steps * 2));
            $new_key_index = ($stepChange + $key_index) % ($base * 2);
            $nextKey = $this->camelotCode[$new_key_index];
        }

        return $nextKey;
    }
    /**
     * @assert ('D#m', 'altkeyname') == 2
     * @assert ('Ebm', 'keyname') == 2
     */
    public function getKeyIndex($key, $keySystem = 'camelotCode')
    {
        //truncate $keyname to last 3 chars; it should not be longer than 3 chars MAX
        // I'm not looking for 'A Minor' or 'Bb Major or 0005M'
        $key = substr($key, -3);

        // trim leading 0's and UPPERcase if necessary
        switch ($keySystem) {
            case 'openKeyCode':
                $keySystem = $this->openKeyCode;
                $key = ltrim(strtoupper($key), '0');
                break;
            case 'altkeyname':
                $keySystem = $this->altkeyname;
                break;
            case 'keyname':
                $keySystem = $this->keyname;
                break;
            default :
                $keySystem = $this->camelotCode;
                $key = ltrim(strtoupper($key), '0');
        }

        return array_search($key, $keySystem);
    }
    /*
     * getKeyMode
     * get the character from the end of a camelot or openkey keycode
     *
     * @param string $key
     * @return boolean
     * TRUE on MAJOR, false on MINOR
     */
    public function getKeyMode($key)
    {
        $key_index = $this->getKeyIndex($key);
        // Major [B] (even) -> Minor [A] (odd)
        return !!($key_index % 2);
    }
    /*
     * getCamelotKeyFromKeyName($keyname)
     * @param string $keyname
     * @return string $camelotKey
     */
    /**
     * @assert ('D#m') == '2A'
     * @assert ('Cm') == '5A'
     */
    public function getCamelotKeyFromKeyName($keyname)
    {
        $camelotKey = null;
        // check keyname and if no good, check altkeyname
        $key_index = (false !== self::getKeyIndex($keyname, 'keyname')) ? self::getKeyIndex($keyname, 'keyname') : self::getKeyIndex($keyname, 'altkeyname');
        if (false !== $key_index) {
            $camelotKey = $this->camelotCode[$key_index];
        }

        return $camelotKey;
    }
    /*
     * getOpenKeyFromCamelotKey($camelotKey)
     * @param string $camelotKey
     * @return string $openKey
     */
    /**
     * @assert ('12A') == '5M'
     */
    public function getOpenKeyFromCamelotKey($camelotKey)
    {
        $openKeyCode = null;
        $key_index = self::getKeyIndex($camelotKey);
        if (false !== $key_index) {
            $openKeyCode = $this->openKeyCode[$key_index];
        }

        return $openKeyCode;
    }
    /*
     * Comments on function descriptions come from:
     * http://blog.dubspot.com/harmonic-mixing-w-dj-endo-part-1/
     */
    /*
     * noChange
     * Staying in the same key (4A – 4A) (F Minor – F Minor)
     * These tracks will both be in the same key and are therefore perfectly compatible harmonically.
     * Playing two tracks in the same key will give the effect that the tracks are singing together.
     *
     * Songs with these keys are returned by default since I pretty much always perform a search.
     */
    /**
     * @assert ('1B') == '1B'
     */
    public function noChange($key)
    {
        return $key;
    }
    /*
     * perfectFourth() // Sub-Dominant
     *
     * Going down a Fifth (-1 on the Camelot Wheel) (4A – 3A) (F Minor – B flat Minor)
     * I like to say this type of mix will take the crowd deeper.
     * The tracks will sound great together.
     * It won’t raise the energy necessarily but will give your listeners goosebumps!
     */
    /**
     * @assert ('1B', -1) == '12B'
     */
    public function perfectFourth($key)
    {
        return (self::calculateNextKey($key, -1));
    }
    /*
     * perfectFifth() // Dominant
     *
     * Moving up a Fifth
     * (+1 on the Camelot Wheel) (4A-5A) (F Minor – C Minor)
     * This will raise the energy in the room.
     *
     * @assert ('12A', 1) == '1A'
     */
    public function perfectFifth($key)
    {
        return (self::calculateNextKey($key, 1));
    }
    /*
     * minorToMajor // xA -> xB
     *
     * Going from Relative Minor to Relative Major
     * (Change Letter on the Camelot Wheel) (4A-4B) (F Minor – A flat Major)
     * This combination will likely sound good because the notes of both scales are the same,
     * but the root note is different.
     * The energy of the room will change dramatically.
     */
    /**
     * @assert ('9B', 0, TRUE) == '9A'
     * @assert ('7A', 0, TRUE) == '7B'
     */
    public function relativeMinorToMajor($key)
    {
        return (self::calculateNextKey($key, 0, true));
    }
    /*
     * minorToMajor // +3 xA -> xB
     *
     * Going from Minor to Major
     * (+3 and change letters on the Camelot wheel) (4A-7B) (F Minor – F Major)
     * While these keys might have 3 notes that are different,
     * the root note is the same and can give a great musical effect on the dancefloor,
     * either brightening the mood or darkening the mood.
     */
    /**
     * @assert ('4B', 3, TRUE) == '7A'
     * @assert ('10A', 3, TRUE) == '1B'
     */
    public function minorToMajor($key)
    {
        return (self::calculateNextKey($key, 3, true));
    }
    /*
     * minorThird()
     *
     * Going up a Minor Third
     * (-3 on the Camelot Wheel) (4A – 1A) (F Minor – A flat Minor)
     * While these scales have 3 notes that are different,
     * I’ve found that they still sound good played together,
     * and tend to raise the energy of a room.
     */
    /**
     * @assert ('4B', -3) == '1B'
     * @assert ('10A', -3) == '7A'
     */
    public function minorThird($key)
    {
        return (self::calculateNextKey($key, -3));
    }
    /*
     * halfStep()
     *
     * Going up a Half Step (Modulation Mixing)
     * (+7 on the Camelot Wheel)(4A-11A)(F Minor – F Sharp Minor)
     * While these two scales have almost no notes in common,
     * musically they shouldn’t sound good together but I’ve found if you plan it right
     * and mix a percussive outro of one song with a percussive intro of another song,
     * and slowly bring in the melody this can have an amazing effect musically and
     * raise the energy of the room dramatically.
     */
    /**
     * @assert ('4B', 7) == '11B'
     * @assert ('10A', 7) == '5A'
     */
    public function halfStep($key)
    {
        return (self::calculateNextKey($key, 7));
    }
    /*
     * I don't know what a fibonacci sequence might do but I suppose someone
     * will have to try it.
     */
    public function fibonacciHarmony($key)
    {
        //return (self::calculateNextKey($key, -2, TRUE));
    }
    /*
     * wholeStep()
     *
     * Going up a whole step (Modulation mixing)
     * (+2 on the Camelot wheel) (4A – 6A) (F Minor – G Minor)
     * This will raise the energy of the room.
     * I like to call it “hands in the air” mixing,
     * and others might call it “Energy Boost mixing”.
     */
    /**
     * @assert ('6B', 2) == '8B'
     * @assert ('12A', 2) == '2A'
     */
    public function wholeStep($key)
    {
        return (self::calculateNextKey($key, 2));
    }
    /*
     * dominantRelative() ??
     *
     * Playing the Dominant Key of the Relative Major / Minor Key
     * (+1 on the Camelot Wheel and change the letter)
     * (4A-5B or 5B-4A)(F Minor to Eb Major)
     * I’ve found this is the best way to go from Major to Minor keys and from Minor
     * to Major because the scales only have one note difference and the
     * combination sounds great
     */
    /**
     * @assert ('4A') == '5B'
     * @assert ('8B') == '7A'
     */
    public function dominantRelative($key)
    {
        // $key_mode = self::getKeyModeOLD($key);
        $step_change = (!self::getKeyMode($key)) ? 1 : -1;

        return (self::calculateNextKey($key, $step_change, true));
    }
    /*
     * goldenRatio
     *
     * I have no idea what this will do
     */
    /**
     * @assert ('6B', 6) == '12B'
     * @assert ('8A', 6) == '2A'
     */
    public function goldenRatioHarmony($key)
    {
        return (self::calculateNextKey($key, 6));
    }
    /*
     * harmonicSeventh
     *
     * I think this has been covered with half step
     */
    public function harmonicSeventh($key)
    {
        //return (calculateNextKey($key, 7, TRUE));
    }
}
