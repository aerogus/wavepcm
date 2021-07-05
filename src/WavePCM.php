<?php declare(strict_types=1);

namespace aerogus\WavePCM;

/**
 * Analyseur/parser/writer d'un fichier RIFF WAVE PCM
 *
 * $wav = WavePCM::loadFile('filename.wav);
 * $wav->displayInfo();
 *
 * $wav = new WavePCM(48000, 16, 2);
 * Usage :
 * $wav = new WavePCM('filename.wav');
 * $wav->setFormat(WavePCM::WAVE_FORMAT_PCM);
 * $wav->setSampleRate(48000);
 * $wav->setBitsPerSample(16);
 * $wav->setNumChannels(2);
 * $wav->push('raw-binary-audiodata'); // longeur multiple de 16 * 2 / 8
 * $wav->write();
 * $wav->push('raw-binary-audiodata'); // longeur multiple de 16 * 2 / 8
 * $wav->write();
 *
 * @author Guillaume Seznec <guillaume@seznec.fr>
 * @see    https://fr.wikipedia.org/wiki/Waveform_Audio_File_Format
 * @see    http://soundfile.sapp.org/doc/WaveFormat/
 */
class WavePCM
{
    /**
     * Chemin complet du fichier à lire et/ou écrire
     */
    protected ?string $filename = null;

    // liste des fréquences d'échantillonage autorisées
    // (techniquement 1Hz à 4.3GHz)
    const SAMPLE_RATES = [
        8000,
       11025,
       22050,
       44100,
       48000,
       96000,
      192000,
    ];

    /**
     * Fréquence d'échantillonage, en Hertz
     */
    protected int $sampleRate = 48000;

    // liste des profondeurs autorisées
    const BIT_DEPTHS = [
        8,
       16,
       24,
       32,
    ];

    /**
     * Nombre de bits par échantillon (profondeur)
     */
    protected int $bitsPerSample = 16;

    // nombre de canaux autorisés
    // (techniquement 1 à 65536)
    const NUMS_CHANNELS = [
        1, // mono
        2, // stéréo
        3, // gauche, droit, centre
        4, // face gauche, face droit, arrière gauche, arrière droit
        5, // gauche, centre, droit, surround (ambiant)
        6, // centre gauche, gauche, centre, centre droit, droit, surround (ambiant)
    ];

    /**
     * Nombre de canaux
     */
    protected int $numChannels = 2;

    const WAVE_FORMAT_PCM        = 0x0001;
    const WAVE_FORMAT_IEEE_FLOAT = 0x0003;
    const WAVE_FORMAT_ALAW       = 0x0006;
    const WAVE_FORMAT_MULAW      = 0x0007;
    const WAVE_FORMAT_EXTENSIBLE = 0xFFFE;

    const FORMATS = [
        self::WAVE_FORMAT_PCM,
        self::WAVE_FORMAT_IEEE_FLOAT,
        self::WAVE_FORMAT_ALAW,
        self::WAVE_FORMAT_MULAW,
        self::WAVE_FORMAT_EXTENSIBLE,
    ];

    protected int $format = self::WAVE_FORMAT_PCM;

    /**
     * Compteur du nombre de blocs d'échantillons (indépendant du nombre de canaux)
     */
    protected int $numSamplesBlocks = 0;

    /**
     * Compteur du nombre total d'échantillons
     */
    protected int $numSamples = 0;

    /**
     * Durée en secondes (valeur calculée)
     */
    protected float $duration = 0.0;

    /**
     * Taille en octets du chunk principal
     * correspond à la taille totale du fichier - 8 (taille de chunkSize et subChunkDataSize)
     * le header faisant 44 octets, la valeur initiale est 36
     */
    protected int $chunkSize = 36;

    /**
     * Taille en octets du subChunk "fmt "
     * cette valeur est fixe: 16 octets
     */
    protected int $subChunkFmtSize = 16;

    /**
     * Taille en octets du subChunk "data"
     * est incrémentée de $this->getSampleBlockSize() octet(s) à chaque ajout d'un SampleBlock
     */
    protected int $subChunkDataSize = 0;

    /**
     * Zone tampon des données audio brutes
     * = contenu du subChunk "data" devant être écrit par write()
     */
    protected string $dataToAppend = '';

    /**
     * Constructeur de l'objet
     *
     * @param string $filename chemin du fichier
     *
     * @throws \Exception
     */
    public function __construct(string $filename = null)
    {
        if (!is_null($filename)) {
            $this->filename = $filename;
            if (is_file($this->filename)) {
                $this->loadFromHeader();
            }
        }
    }

    public function getSampleRate(): int
    {
        return $this->sampleRate;
    }

    public function setSampleRate(int $sampleRate)
    {
        if (!in_array($sampleRate, self::SAMPLE_RATES)) {
            throw new \Exception('unknown sampling rate');
        }
        $this->sampleRate = $sampleRate;
    }

    public function getBitsPerSample(): int
    {
        return $this->bitsPerSample;
    }

    public function setBitsPerSample(int $bitsPerSample)
    {
        if (!in_array($bitsPerSample, self::BIT_DEPTHS)) {
            throw new \Exception('profondeur inconnue');
        }
        $this->bitsPerSample = $bitsPerSample;
    }

    public function getNumChannels(): int
    {
        return $this->numChannels;
    }

    public function setNumChannels(int $numChannels)
    {
        if (!in_array($numChannels, self::NUMS_CHANNELS)) {
            throw new \Exception('nombre de canaux inconnu');
        }
        $this->numChannels = $numChannels;
    }

    public function setFormat(int $format)
    {
        if (!in_array($format, self::FORMATS)) {
            throw new \Exception('format inconnu');
        }
        $this->format = $format;
    }

    public function getFormat(): int
    {
        return $this->format;
    }

    /**
     * Retourne en octets la taille totale des données brutes (= du subChunk "data")
     * (doit être identique à $this->subChunkDataSize)
     */
    public function getSubchunkDataSize(): int
    {
        return $this->numSamples * $this->numChannels * $this->bitsPerSample / 8;
    }

    /**
     * Retourne le débit en octets par seconde
     * (devrait être identique à $this->byteRate)
     */
    public function getByteRate(): int
    {
        return $this->sampleRate * $this->numChannels * $this->bitsPerSample / 8;
    }

    /**
     * Retourne le nombre d'octets pour un bloc d'échantillons en incluant tous les canaux
     */
    public function getSampleBlockSize(): int
    {
        return $this->numChannels * $this->bitsPerSample / 8;
    }

    /**
     * Retourne la chaine binaire représentant le header standard de 44 octets, alimenté par les propriétés
     * de l'objet qui doivent être bien settés ou calculés
     */
    protected function getRawHeader(): string
    {
        return
            // ChunkId, fixe
            'RIFF'                                  // offset 00, 4 octets, big endian
            // ChunkSize
          . pack('V', $this->chunkSize)             // offset 04, 4 octets, little endian
            // Format, fixe
          . 'WAVE'                                  // offset 08, 4 octets, big endian
            // SubchunkFmtId, fixe
          . 'fmt '                                  // offset 12, 4 octets, big endian
            // SubchunkFmtSize, fixé à 16 octets
          . pack('V', $this->subChunkFmtSize)       // offset 16, 4 octets, little endian
            // AudioFormat (1 = PCM)
          . pack('v', $this->format)                // offset 20, 2 octets, little endian
            // NumChannels (1 = mono, 2 = stereo)
          . pack('v', $this->numChannels)           // offset 22, 2 octets, little endian
            // SampleRate (ex: 48000)
          . pack('V', $this->sampleRate)            // offset 24, 4 octets, little endian
            // ByteRate (en octets par seconde)
          . pack('V', $this->getByteRate())         // offset 28, 4 octets, little endian
            // SampleBlockSize (taille d'un bloc d'échantillons, en octets)
          . pack('v', $this->getSampleBlockSize())  // offset 32, 2 octets, little endian
            // BitsPerSample (profondeur)
          . pack('v', $this->bitsPerSample)         // offset 34, 2 octets, little endian
            // SubchunkDataId, fixe
          . 'data'                                  // offset 36, 4 octets, big endian
            // SubchunkDataSize
          . pack('V', $this->subChunkDataSize);     // offset 40, 4 octets, little endian
    }

    /**
     * Parse le header de l'entête du fichier et charge les propriétés de l'objet
     *
     * @return bool
     * @throws \Exception
     */
    public function loadFromHeader(): bool
    {
        if (($fh = fopen($this->filename, 'rb')) !== false) {
            if ($buffer = fread($fh, 44)) { // 44 octets est la taille fixe du header, pas besoin de lire plus
                if (substr($buffer, 0, 4) !== 'RIFF') {
                    throw new \Exception('not a valid RIFF file');
                }
                // lecture uniquement des valeurs nécessaires
                $this->chunkSize        = (int) unpack('V', substr($buffer,  4, 4))[1];
                $this->numChannels      = (int) unpack('v', substr($buffer, 22, 2))[1];
                $this->sampleRate       = (int) unpack('V', substr($buffer, 24, 4))[1];
                $this->bitsPerSample    = (int) unpack('v', substr($buffer, 34, 2))[1];
                $this->subChunkDataSize = (int) unpack('V', substr($buffer, 40, 4))[1];
                $this->numSamplesBlocks = $this->subChunkDataSize / $this->getSampleBlockSize();
                $this->numSamples       = $this->numSamplesBlocks * $this->numChannels;
                $this->duration         = $this->numSamplesBlocks / $this->sampleRate;

                return true;
            } else {
                throw new \Exception('lecture du fichier impossible');
            }
        }
        throw new \Exception('fichier introuvable');
    }

    /**
     * Ajoute des données brutes audio (1 ou plusieurs sampleBlocks) dans la zone tampon
     * et met à jour différents compteurs
     *
     * @param string $data (la longueur doit être multiple de $this->getSampleBlockSize())
     */
    public function pushSampleBlock(string $data): void
    {
        $receivedLength = strlen($data);
        $expectedLength = $this->getSampleBlockSize();
        if ($receivedLength % $this->getSampleBlockSize() !== 0) {
            throw new \Exception(sprintf('taille du block de données non valide : reçus %d, attendus multiple de %s', $receivedLength, $expectedLength));
        }

        $samplesBlocksCount = $receivedLength / $this->getSampleBlockSize();
        $samplesCount = $samplesBlocksCount * $this->numChannels;
        $duration = $samplesBlocksCount / $this->sampleRate;

        $this->dataToAppend .= $data;

        // mises à jour de compteurs
        $this->chunkSize        += $receivedLength;
        $this->subChunkDataSize += $receivedLength;
        $this->numSamplesBlocks += $samplesBlocksCount;
        $this->numSamples       += $samplesCount;
        $this->duration         += $duration;
    }

    /**
     * Écrit le fichier après avoir recalculé les compteurs de l'entête
     *
     * @throws \Exception
     */
    public function write()
    {
        if (!$this->filename) {
            throw new \Exception('missing filename');
        }

        // à la première exécution, on créé le fichier et on écrit le header
        if (!is_file($this->filename)) {
            if ($fh = fopen($this->filename, 'x')) { // x, le fichier ne doit pas exister, ouverture en écriture
                fwrite($fh, $this->getRawHeader());
                //fflush($fh);
                fclose($fh);
            }
        }

        // ajout données brutes à la fin du fichier + maj des compteurs
        if ($this->dataToAppend) {
            if ($fh = fopen($this->filename, 'a')) {
                fwrite($fh, $this->dataToAppend);
                fclose($fh);
                $this->dataToAppend = '';
                $this->close();
            }
        }
    }

    /**
     * Clôture le fichier et met à jour les bons compteurs
     */
    public function close(): bool
    {
        // c = ouverture en écriture, mode binaire, avec possibilité d'écrasement
        if ($fh = fopen($this->filename, 'cb')) {

            // maj chunkSize: offset 04, 4 octets, little endian
            fseek($fh, 4);
            fwrite($fh, pack('V', $this->chunkSize));

            // maj subChunkDataSize: offset 40, 4 octets, little endian
            fseek($fh, 40);
            fwrite($fh, pack('V', $this->subChunkDataSize));

            fflush($fh);
            fclose($fh);

            return true;
        }

        return false;
    }

    /**
     * Fait un parsing de l'entête brutes du fichier (44 octets)
     *
     * @return array
     */
    public function dumpRawHeader(): array
    {
        $data = [];

        if (is_file($this->filename) && ($handle = fopen($this->filename, 'rb')) !== false) {
            if ($buffer = fread($handle, 44)) {
                // la représentation ASCII doit être égale à "RIFF"
                $data['ChunkId'] = substr($buffer, 0, 4);
                // la taille total du fichier - 8 (les 2 compteurs de 4 octets)
                $data['ChunkSize'] = unpack('V', substr($buffer, 4, 4))[1] + 8;
                // calcul de la taille réelle du fichier
                clearstatcache();
                $data['RealFileSize'] = filesize($this->filename);
                // la représentation ASCII doit être égale à "WAVE"
                $data['Format'] = substr($buffer, 8, 4);
                // la représentation ASCII doit être égale à "fmt "
                $data['SubchunkFmtId'] = substr($buffer, 12, 4);
                // nombre d'octets du bloc
                $data['SubchunkFmtSize'] = unpack('V', substr($buffer, 16, 4))[1];
                // format du stockage (1 = PCM)
                $data['AudioFormat'] = unpack('v', substr($buffer, 20, 2))[1];
                // nombre de canaux (1 à 6)
                $data['NumChannels'] = unpack('v', substr($buffer, 22, 2))[1];
                // fréquence d'échantillonage en Hertz
                $data['SampleRate'] = unpack('V', substr($buffer, 24, 4))[1];
                // nombre d'octets par seconde
                $data['ByteRate'] = unpack('V', substr($buffer, 28, 4))[1];
                // nombre d'octets par bloc d'échantillonnage
                $data['SampleBlockSize'] = unpack('v', substr($buffer, 32, 2))[1];
                // nombre de bits par échantillon
                $data['BitsPerSample'] = unpack('v', substr($buffer, 34, 2))[1];
                // la représentation ASCII doit être égale à "data"
                $data['SubchunkDataId'] = substr($buffer, 36, 4);
                // nombre d'octets de données (taille du fichier - toutes les entêtes)
                $data['SubchunkDataSize'] = unpack('V', substr($buffer, 40, 4))[1];
            }
            fclose($handle);
        }

        return $data;
    }

    /**
     * Affichage formaté de l'entête
     */
    public function displayInfo()
    {
        $props = $this->dumpRawHeader();

        $out = '';
        foreach ($props as $propName => $propValue) {
            $out .= str_pad($propName, 17, ' ', STR_PAD_RIGHT) . ": " . $propValue . "\n";
        }
        echo $out;
    }
}
