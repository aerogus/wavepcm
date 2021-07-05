# Bibliothèque Wave PCM

Bibliothèque PHP pour gérer les fichier audio RIFF WAVE PCM

## Installation

via composer

```
composer require aerogus/wavepcm
```

## Analyse de l'entête

exemple :

```
#!/usr/bin/env php
<?php

require_once __DIR__ . 'vendor/autoload.php';

use Aerogus\WavePCM\WavePCM;

$wav = new WavePCM('example.wav');
$wav->displayInfo();

```

```
$ php t.php
ChunkId          : RIFF
ChunkSize        : 2816600872
RealFileSize     : 7111568168
Format           : WAVE
SubchunkFmtId    : fmt
SubchunkFmtSize  : 16
AudioFormat      : 1
NumChannels      : 2
SampleRate       : 48000
ByteRate         : 192000
SampleBlockSize  : 4
BitsPerSample    : 16
SubchunkDataId   : data
SubchunkDataSize : 2816600828
```

## Génération d'un fichier sonore

voir dans le répertoire tests
