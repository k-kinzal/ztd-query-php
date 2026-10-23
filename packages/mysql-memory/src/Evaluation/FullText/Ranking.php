<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\FullText;

use LogicException;

/**
 * Calculates relevance from document frequencies and the chosen storage engine.
 *
 * InnoDB uses the documented TF * IDF * IDF formula. SQL observations on 8.4.7
 * establish duplicate search-word counting, the nonzero common-word floor and
 * single-precision accumulation. MyISAM's length normalization was measured by
 * varying document length and term repetition independently.
 * Source: https://dev.mysql.com/doc/refman/8.0/en/fulltext-boolean.html.
 *
 * @visibility MySqlMemory
 */
final class Ranking
{
    /**
     * @param array<string, int> $document Indexed term frequencies of the current document
     * @param array<string, int> $frequencies Number of documents containing each indexed term
     * @param array<string, int> $query Search word frequencies
     */
    public function natural(array $document, array $frequencies, int $total, array $query, bool $myIsam): float
    {
        $score = 0.0;
        foreach ($query as $word => $repetitions) {
            $count = $document[$word] ?? 0;
            $matching = $frequencies[$word] ?? 0;
            if ($count === 0 || $matching === 0) {
                continue;
            }
            if ($myIsam) {
                $score += $this->myIsam($document, $count, $matching, $total) * $repetitions;
            } else {
                $ratio = (float) ($total / ($matching * $repetitions));
                $idf = log10($ratio === 1.0 ? 1.0001 : $ratio);
                $score = $this->single($score + $this->single($count * $idf * $idf));
            }
        }
        return $this->single($score);
    }

    /**
     * @param array<string, int> $document All indexed document term frequencies
     */
    public function myIsam(array $document, int $count, int $matching, int $total): float
    {
        if ($matching * 2 >= $total) {
            return 0.0;
        }
        $unique = count($document);
        $normalization = array_sum(array_map(static fn (int $frequency): float => 1.0 + log($frequency), $document));
        $weight = $this->single((1.0 + log($count)) * $unique / $normalization / (1.0 + 0.0115 * $unique));
        return $weight * log(($total - $matching) / $matching);
    }

    /**
     * Preserves the observed binary32 result in PHP's binary64 float.
     *
     * @throws LogicException When the runtime cannot unpack a binary32 value
     */
    public function single(float $number): float
    {
        $single = unpack('g', pack('g', $number));
        if ($single === false || !is_float($single[1] ?? null)) {
            throw new LogicException('A packed binary32 number must unpack to a float.');
        }
        return $single[1];
    }
}
