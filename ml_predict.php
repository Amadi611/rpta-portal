<?php
/**
 * ML inference for the RPTA Assistant.
 * Trained model: LinearSVC + TF-IDF word (1-2 gram) and character (2-5 gram)
 * trained on the 400-question starter dataset in data/faqs.json.
 */
function ml_model() {
    static $model = null;
    if ($model === null) {
        $path = __DIR__ . '/data/ml_model.json';
        if (!is_file($path)) return null;
        $raw = file_get_contents($path);
        $model = json_decode($raw, true);
        if (!is_array($model)) return null;
    }
    return $model;
}

function ml_word_tokens($text) {
    $text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
    preg_match_all('/[\p{L}\p{N}_]{2,}/u', $text, $m);
    return $m[0] ?? [];
}

function ml_char_ngrams($text, $min=2, $max=5) {
    $text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
    $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
    $out = [];
    $n = count($chars);
    for ($k=$min; $k<=$max; $k++) {
        for ($i=0; $i+$k <= $n; $i++) {
            $out[] = implode('', array_slice($chars, $i, $k));
        }
    }
    return $out;
}

function ml_tfidf_block($items, $vocab, $idf) {
    $counts = [];
    foreach ($items as $item) {
        if (isset($vocab[$item])) {
            $idx = (int)$vocab[$item];
            $counts[$idx] = ($counts[$idx] ?? 0) + 1;
        }
    }
    $vec = [];
    $norm = 0.0;
    foreach ($counts as $idx=>$count) {
        $value = (1.0 + log((float)$count)) * (float)$idf[$idx];
        $vec[$idx] = $value;
        $norm += $value * $value;
    }
    $norm = sqrt($norm);
    if ($norm > 0) {
        foreach ($vec as $idx=>$value) $vec[$idx] = $value / $norm;
    }
    return $vec;
}

function ml_predict($question) {
    $m = ml_model();
    if (!$m) return null;

    $word = ml_tfidf_block(
        ml_word_tokens($question),
        $m['word_vocab'],
        $m['word_idf']
    );
    $char = ml_tfidf_block(
        ml_char_ngrams($question, 2, 5),
        $m['char_vocab'],
        $m['char_idf']
    );

    $scores = [];
    $classes = $m['classes'];
    $coef = $m['coef'];
    $intercept = $m['intercept'];
    $wordN = count($m['word_idf']);

    for ($c=0; $c<count($classes); $c++) {
        $score = (float)$intercept[$c];
        foreach ($word as $idx=>$value) {
            $score += $value * (float)$coef[$c][$idx];
        }
        foreach ($char as $idx=>$value) {
            $score += $value * (float)$coef[$c][$wordN + $idx];
        }
        $scores[$c] = $score;
    }

    arsort($scores);
    $keys = array_keys($scores);
    $best = (int)$keys[0];
    $second = isset($keys[1]) ? (float)$scores[$keys[1]] : 0.0;
    $margin = (float)$scores[$best] - $second;
    $intent = $classes[$best];

    return [
        'intent' => $intent,
        'score' => (float)$scores[$best],
        'margin' => $margin,
        'answer' => $m['answers'][$intent] ?? null,
        'model' => $m['model_type'],
        'trained_questions' => (int)$m['trained_questions']
    ];
}
?>
