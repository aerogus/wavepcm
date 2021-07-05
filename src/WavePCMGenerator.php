<?php declare(strict_types=1);

namespace aerogus;

require_once 'WavePCM.php';

/**
 * Générateur de forme d'onde
 *
 * Usage:
 * $length = 10; // sec.
 * $wavgen = new \aerogus\WavePCMGenerator('filename.wav');
 * $wavgen->setSampleRate(48000);
 * $wavgen->setBitsPerSample(16);
 * $wavgen->setNumChannels(2);
 * for ($i = 0 ; $i < $wavgen->getSampleRate() * $length ; $i++) {
 *     $wavgen->pushRandSampleBlock();
 * }
 * $wavgen->write();
 *
 * @author Guillaume Seznec <guillaume@seznec.fr>
 */
class WavePCMGenerator extends WavePCM
{
    // différents types de formes d'onde
    const TYPES = [
        'silent',
        'sinus',
        'saw',
        'random',
    ];

    const SAMPLE = [
        // /!\ valeurs non signées en 8 bits
        8 => [
            'mini' =>   0,
            'zero' => 128,
            'maxi' => 255,
        ],
        // /!\ valeurs signées en 16 bits
        16 => [
            'mini' => -32768,
            'zero' =>      0,
            'maxi' =>  32767,
        ],
        24 => [
            'mini' =>      0,
            'zero' =>      0,
            'maxi' =>      0,
        ],
        32 => [
            'mini' =>      0,
            'zero' =>      0,
            'maxi' =>      0,
        ]
    ];

    protected static $max_amplitude = .25;

    // génération dents de scie
    const SAW_AMPLITUDE  = .8;  // en % d'amplitude max
    const SAW_FREQUENCY = 4000; // en Hertz

    /**
     * Valeur du sample courant
     */
    protected $currSample = 0;

    /**
     * Valeur absolue de la pente de la dent de scie
     */
    public $offset = 440;

    protected $type = null;

    public function getType()
    {
        return $this->type;
    }

    public function setType($type)
    {
        if (!in_array($type, self::TYPES)) {
            throw new \Exception('type inconnu');
        }
        $this->type = $type;
    }

    public function pushSinSampleBlock(float $frequency)
    {
        $sampleBlock = '';
        $samples_per_period = $this->sampleRate / $frequency;

        switch ($this->bitsPerSample) {
            case 16:
                $sample = (int) floor(sin(2 * M_PI * $this->numSamplesBlocks / $samples_per_period) * 128) + 128;
                for ($c = 0 ; $c < $this->numChannels ; $c++) {
                    $sampleBlock .= pack('s', $sample);
                }
                break;
            case 8:
                $sample = (int) abs(floor(sin(2 * M_PI * $this->numSamplesBlocks / $samples_per_period) * self::$max_amplitude * 128) + 128);
                for ($c = 0 ; $c < $this->numChannels ; $c++) {
                    $sampleBlock .= pack('C', $sample);
                }
                break;
            default:
                throw new Exception('Profondeur non implémentée');
                break;
        }

        $this->pushSampleBlock($sampleBlock);
    }

    /**
     * Ajoute un échantillon silencieux sur chacun des canaux dans la zone tampon
     */
    public function pushZeroSampleBlock()
    {
        $sampleBlock = '';

        switch ($this->bitsPerSample) {
            case 16:
                $sample = self::SAMPLE[16]['zero'];
                for ($c = 0 ; $c < $this->numChannels ; $c++) {
                    $sampleBlock .= pack('s', $sample);
                }
                break;
            case 8:
                $sample = self::SAMPLE[8]['zero'];
                for ($c = 0 ; $c < $this->numChannels ; $c++) {
                    $sampleBlock .= pack('C', $sample);
                }
                break;
            default:
                throw new Exception('Profondeur non implémentée');
                break;
        }

        $this->pushSampleBlock($sampleBlock);
    }

    /**
     * Ajoute un échantillon aléatoire sur chacun des canaux dans la zone tampon
     */
    public function pushRandomSampleBlock()
    {
        $sampleBlock = '';

        switch ($this->bitsPerSample) {
            case 16:
                for ($c = 0 ; $c < $this->numChannels ; $c++) {
                    // s = entier court signé sur 16 bits
                    $sample = rand((int) round(self::SAMPLE[16]['mini'] * self::$max_amplitude), (int) round(self::SAMPLE[16]['maxi'] * self::$max_amplitude));
                    $sampleBlock .= pack('s', $sample);
                }
                break;
            case 8:
                $sampleBlock = '';
                for ($c = 0 ; $c < $this->numChannels ; $c++) {
                    // C = caractère non signé
                    $sample = rand((int) round(self::SAMPLE[8]['mini'] * self::$max_amplitude), (int) round(self::SAMPLE[8]['maxi'] * self::$max_amplitude));
                    $sampleBlock .= pack('C', $sample);
                }
                break;
            default:
                throw new \Exception('Résolution non implémentée');
                break;
        }

        $this->pushSampleBlock($sampleBlock);
    }

    /**
     * Ajoute un échantillon pour former une onde en dents de scie
     */
    public function pushSawSampleBlock()
    {
        $sampleBlock = '';

        switch ($this->bitsPerSample) {
            case 16:
                $this->currSample += $this->offset;
                if (($this->currSample > (self::SAMPLE[16]['maxi'] * self::SAW_AMPLITUDE)) || ($this->currSample < (self::SAMPLE[16]['mini'] * self::SAW_AMPLITUDE))) {
                    $this->offset *= -1;
                }
                for ($c = 0 ; $c < $this->numChannels ; $c++) {
                    $sampleBlock .= pack('s', $this->currSample);
                }
                break;
            case 8:
                $this->currSample += $this->offset;
                if (($this->currSample > (self::SAMPLE[8]['maxi'] * self::SAW_AMPLITUDE)) || ($this->currSample < (self::SAMPLE[8]['mini'] * self::SAW_AMPLITUDE))) {
                    $this->offset *= -1;
                }
                for ($c = 0 ; $c < $this->numChannels ; $c++) {
                    $sampleBlock .= pack('C', $this->currSample);
                }
                break;
            default:
                throw new \Exception('Résolution non implémentée');
                break;
        }

        $this->pushSampleBlock($sampleBlock);
    }
}