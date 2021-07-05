#!/usr/bin/env php
<?php declare(strict_types=1);

/**
 * Parser de fichier RIFF WAVE PCM
 *
 * @author Guillaume Seznec <guillaume@seznec.fr>
 *
 * @param string filename
 */

require_once __DIR__ . '/../vendor/autoload.php';

use \Aerogus\WavePCM\WavePCM;
use \Aerogus\WavePCM\WavePCMGenerator;

if ($argc < 2) {
    $help = <<<'EOT'
-------
wavdump
-------
Usage: wavdump.php filename.wav

EOT;
    die($help);
}

$filename = $argv[1];
(new WavePCM($filename))->displayInfo();
