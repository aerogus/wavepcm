#!/usr/bin/env php
<?php declare(strict_types=1);

/**
 * Générateur de fichier .wav
 *
 * @author Guillaume Seznec <guillaume@seznec.fr>
 */

require_once __DIR__ . '/../vendor/autoload.php';

use \Aerogus\WavePCM\WavePCM;
use \Aerogus\WavePCM\WavePCMGenerator;

define('WAV_FILE', 'test.wav');
define('LENGTH', .5); // secondes

echo "génération de " . WAV_FILE . " de durée " . LENGTH . " secondes\n";

$wavgen = new WavePCMGenerator(WAV_FILE);
$wavgen->setFormat(WavePCM::WAVE_FORMAT_PCM);
$wavgen->setSampleRate(48000);
$wavgen->setBitsPerSample(8);
$wavgen->setNumChannels(2);

// correspondance note / fréquence
// @see https://fr.wikipedia.org/wiki/Fr%C3%A9quences_des_touches_du_piano
$notes = [
    'C4' => 523.251,
    'D4' => 587.333,
    'E4' => 659.255,
];

// au clair de la lune
$score = [
    'C4', 'C4', 'C4', 'D4', 'E4', 'E4', 'D4', 'D4', 'C4', 'E4', 'D4', 'D4', 'C4', 'C4', '', '',
];

foreach ($score as $note) {
    for ($i = 0 ; $i < $wavgen->getSampleRate() * LENGTH ; $i++) {
        if ($note) {
            $wavgen->pushSinSampleBlock($notes[$note]);
        } else {
            $wavgen->pushZeroSampleBlock();
        }
    }
}
$wavgen->write();
$wavgen->close();

echo "Génération OK\n";
echo "Dump " . WAV_FILE . "\n";

$wav = new WavePCM(WAV_FILE);
$wav->displayInfo();
