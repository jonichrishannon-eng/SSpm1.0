<?php

$jsonStr = file_get_contents('https://world.openfoodfacts.org/api/v0/product/4066600204404.json');
$json = json_decode($jsonStr, true);

$brands = $json['product']['brands'] ?? "";
$name = $json['product']['product_name_de'] ?? $json['product']['product_name'] ?? "";

if (!empty($brands) && stripos($name, $brands) === false) {
    if (strpos($brands, ',') !== false) {
        $brands = explode(',', $brands)[0]; // take first brand if multiple
    }
    $offName = trim($brands . " " . $name);
} else {
    $offName = trim($name);
}

echo "OFF Name: $offName\n";

$dbName = "Paulaner Spezi ZERO 0,5l Glas";

function cleanString($str) {
    if (empty($str)) return "";
    return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $str));
}

$cleanOFF = cleanString($offName);
$cleanJTL = cleanString($dbName);

// Let's refine splitting into words
// We can use non-alphanumeric chars as separators
$wordsOFF = preg_split('/[^a-zA-Z0-9]+/', strtolower($offName), -1, PREG_SPLIT_NO_EMPTY);

$matchedWords = 0;
foreach ($wordsOFF as $w) {
    if (strpos(strtolower($dbName), $w) !== false) {
        $matchedWords++;
    }
}
$wordScore = (count($wordsOFF) > 0) ? ($matchedWords / count($wordsOFF)) * 100 : 0;

$lev = levenshtein($cleanOFF, $cleanJTL);
$maxLen = max(strlen($cleanOFF), strlen($cleanJTL));
$levScore = ($maxLen > 0) ? (1 - $lev / $maxLen) * 100 : 0;

$currentScore = ($wordScore * 0.7) + ($levScore * 0.3);

echo "WordScore: $wordScore\n";
echo "LevScore: $levScore\n";
echo "Total Score: $currentScore\n";
