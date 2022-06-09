<?php

// The maximum word count for a phrase
const PHRASE_WORD_MAX = 8;

// The minimum word count for what is considered a phrase
const PHRASE_WORD_MIN = 3;

// The core multiplier applied for phrase word length
const PHRASE_LENGTH_SCORE_MULTIPLIER = 2;

// Regex patters of phrases that should be ignored upon filtering
const PHRASE_IGNORE_PATTERNS = [
    '/memory of \S+$/', // Filters out most name phrases (eg. "memory of Dan")
];

// Fetch our inscriptions and build a phrase map from these
$inscriptions = cleanInscriptions(loadInscriptions());
$map = buildPhraseMap($inscriptions);

// Build our sorted score list and output it
$scoreList = phraseMapToScoredPhraseList($map);
sortScoredPhraseList($scoreList);
outputScoredPhraseList($scoreList, "all");

// Build a cleaned-up filter and de-duped top-250 list
$filtered = filterScoredPhraseList($scoreList);
$deduped = deduplicateScoredPhraseList($filtered);
outputScoredPhraseList(array_slice($deduped, 0, 250), "top-250");

echo "Finished.\nMemory usage: " . memory_get_usage() . "\n";

/**
 * Output the given scored phrase list data to a csv of the given filename.
 * @param array{score: int, phrase: string}[] $list
 */
function outputScoredPhraseList(array $list, string $filename): void {
    $outFile = __DIR__ . "/output/{$filename}.csv";
    $stream = fopen($outFile, 'w');
    fwrite($stream, "phrase, score\n");

    foreach ($list as $phraseItem) {
        $line = "{$phraseItem['phrase']}, {$phraseItem['score']}\n";
        fwrite($stream, $line);
    }

    fclose($stream);
}

/**
 * Perform a basic de-duplicate step of the given scored phrase list which cuts out
 * lesser-scored results that are wholly contained in higher-scored results.
 * The provided list should be sorted before being passed to this function.
 * Note: This is real basic, dumb and memory-intensive.
 * @param array{score: int, phrase: string}[] $list
 * @return array{score: int, phrase: string}[]
 */
function deduplicateScoredPhraseList(array $list): array {
    $dedup = [];
    $phrases = '';

    foreach ($list as $phraseItem) {
        $phrase = $phraseItem['phrase'];
        if (strpos($phrases, $phrase) === false) {
            $dedup[] = $phraseItem;
            $phrases .= ',' . $phrase;
        }
    }

    return $dedup;
}

/**
 * @param array{score: int, phrase: string}[] $list
 * @return array{score: int, phrase: string}[]
 */
function filterScoredPhraseList(array $list): array {
    $filtered = [];

    listLoop: foreach ($list as $phraseItem) {
        $phrase = $phraseItem['phrase'];
        foreach (PHRASE_IGNORE_PATTERNS as $filter) {
            if (preg_match($filter, $phrase)) {
                continue 2;
            }
        }

        $filtered[] = $phraseItem;
    }

    return $filtered;
}

/**
 * Sort the given scored phrase list by score descending from largest to smallest.
 * @param array{score: int, phrase: string}[] $list
 */
function sortScoredPhraseList(array &$list): void {
    usort($list, function (array $a, array $b) {
        return $b['score'] - $a['score'];
    });
}

/**
 * Convert a phrase map into a scored phrase list which has the following format:
 * [['phrase' => 'my phrase', 'score' => 5], ['phrase' => 'another phrase', 'score' => 3]]
 * Scoring is based upon depth/length and frequency.
 * @return array<string, float>
 */
function phraseMapToScoredPhraseList(array $phaseMap, array $words = [], array &$scoreList = null): array {
    if (is_null($scoreList)) {
        $scoreList = [];
    }

    $depth = count($words);
    if ($depth >= PHRASE_WORD_MIN) {
        $phrase = implode(' ', $words);
        $depthMultiplier = (($depth + 1) - PHRASE_WORD_MIN) * PHRASE_LENGTH_SCORE_MULTIPLIER;
        $frequency = ($phaseMap['.'] ?? 0);
        $score = $frequency * $depthMultiplier;
        if ($frequency > 1) {
            $scoreList[] = ['score' => $score, 'phrase' => $phrase];
        }
    }


    foreach ($phaseMap as $key => $value) {
        if (is_array($value)) {
            $subWordList = $words;
            $subWordList[] = $key;
            phraseMapToScoredPhraseList($value, $subWordList, $scoreList);
        }
    }

    return $scoreList;
}

/**
 * Build a phrase map from the given array of lines.
 * A phrase map is a nested array structure where keys are words in the phrase
 * and values are forward-adjacent terms. Values keyed by '.' track the phrase count to that depth.
 * Example: ['barry' => ['.' => 4, 'was' => ['.' => 2, 'here' => ['.' => 1]]]]
 * @param string[] $lines
 * @return array<string, array>
 */
function buildPhraseMap(array $lines) : array {
    $map = [];

    foreach ($lines as $line) {
        addLineToPhraseMap($line, $map);
    }

    return $map;
}

/**
 * Add a single line into the given phrase map.
 */
function addLineToPhraseMap(string $line, array &$phraseMap): void {
    $words = explode(' ', $line);
    $wordCount = count($words);
    for ($i = 0; $i < $wordCount; $i++) {
        $phraseMax = min($i + PHRASE_WORD_MAX, $wordCount);
        $cMapItem = &$phraseMap;
        for ($j = $i; $j < $phraseMax; $j++) {
            $word = $words[$j];

            if (!isset($cMapItem[$word])) {
                $cMapItem[$word] = [
                    '.' => 0
                ];
            }

            $cMapItem = &$cMapItem[$word];
            $cMapItem['.']++;
        }
    }
}

/**
 * Clean each line of the given inscriptions.
 * Normalised whitespace and removes some punctuation for better tokenizing.
 * @param string[] $lines
 * @return string[]
 */
function cleanInscriptions(array $lines): array {
    return array_map(function($line) {
        $line = preg_replace('/\s+/', ' ', $line);
        $line = preg_replace('/[.,;]/', '', $line);
        return strtolower(trim($line));
    }, $lines);
}

/**
 * Load all inscriptions as an array of strings from a local JSON file
 * cache if existing otherwise the openbenches API endpoint.
 * @return string[]
 */
function loadInscriptions(): array {
    $cacheFile = __DIR__ . '/cache/inscriptions.json';

    if (file_exists($cacheFile)) {
        return json_decode(file_get_contents($cacheFile));
    }

    $apiDataString = file_get_contents('https://openbenches.org/api/v1.0/data.json/?truncated=false');
    $jsonApiData = substr($apiDataString, 14);
    $apiData = json_decode($jsonApiData);

    $inscriptions = array_map(function($bench) {
        return $bench->properties->popupContent ?? '';
    }, $apiData->features);

    file_put_contents($cacheFile, json_encode($inscriptions));

    return $inscriptions;
}