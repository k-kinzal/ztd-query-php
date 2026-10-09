<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Pattern;

use Closure;
use Transliterator;

/**
 * Lists the code points of a set as the ranges of a PCRE class.
 *
 * A set ICU names by a UnicodeSet pattern is read from ICU a block of code points at a time, by a
 * transliterator whose filter is the complement of the set and which removes what it filters, so
 * that only the members of the block are left. Surrogates, which UTF-8 text cannot hold, and a set
 * without a pattern are tested one code point at a time.
 * Source: https://unicode-org.github.io/icu/userguide/strings/unicodeset.html,
 * https://unicode-org.github.io/icu/userguide/transforms/general/.
 *
 * @visibility MySqlMemory\Evaluation\Function\Pattern
 */
final class Listing
{
    /**
     * The number of code points in a block.
     */
    public const BLOCK = 0x1000;

    /**
     * The UTF-8 text of the code points of each block, surrogates aside, by the first code point of the block.
     *
     * @var array<int, string>
     */
    public static array $texts = [];

    /**
     * Writes the ranges of the code points of a set below a limit: those of an ICU UnicodeSet pattern, or those that pass the test when there is no pattern or ICU does not read it.
     *
     * @param Closure(int): bool $test Whether a code point is in the set
     * @param string|null $set The UnicodeSet pattern of the same set
     * @param int $limit The code point after the last one listed, a multiple of BLOCK
     */
    public function ranges(Closure $test, ?string $set = null, int $limit = 0x110000): string
    {
        $remover = $set === null ? null : Transliterator::create('[^' . $set . '] Remove');
        $runs = [];
        for ($first = 0; $first < $limit; $first += self::BLOCK) {
            $surrogates = $first <= 0xDFFF && $first + self::BLOCK > 0xD800;
            foreach ($remover === null || $surrogates ? $this->tested($first, $test) : $this->read($first, $remover) as [$start, $end]) {
                $last = array_key_last($runs);
                if ($last !== null && $runs[$last][1] === $start - 1) {
                    $runs[$last][1] = $end;
                } else {
                    $runs[] = [$start, $end];
                }
            }
        }

        return implode('', array_map(static fn (array $run): string => sprintf($run[0] === $run[1] ? '\x{%X}' : '\x{%X}-\x{%X}', $run[0], $run[1]), $runs));
    }

    /**
     * Answers the runs of the code points of a block that a transliterator leaves, in order.
     *
     * @return list<array{int, int}>
     */
    public function read(int $first, Transliterator $remover): array
    {
        $text = $this->text($first);
        $left = $remover->transliterate($text);
        if ($left === $text) {
            return [[$first, $first + self::BLOCK - 1]];
        }
        $codes = $left === false || $left === '' ? false : unpack('N*', mb_convert_encoding($left, 'UTF-32BE', 'UTF-8'));

        return $codes === false ? [] : $this->runs(array_values(array_filter($codes, is_int(...))));
    }

    /**
     * Answers the runs of the code points of a block that pass a test, in order.
     *
     * @param Closure(int): bool $test
     * @return list<array{int, int}>
     */
    public function tested(int $first, Closure $test): array
    {
        return $this->runs(array_values(array_filter(range($first, $first + self::BLOCK - 1), $test)));
    }

    /**
     * Answers the runs of consecutive code points of an ascending list.
     *
     * @param list<int> $codes
     * @return list<array{int, int}>
     */
    public function runs(array $codes): array
    {
        $runs = [];
        foreach ($codes as $code) {
            $last = array_key_last($runs);
            if ($last !== null && $runs[$last][1] === $code - 1) {
                $runs[$last][1] = $code;
            } else {
                $runs[] = [$code, $code];
            }
        }

        return $runs;
    }

    /**
     * Answers the UTF-8 text of the code points of a block, surrogates aside, written once for each block.
     */
    public function text(int $first): string
    {
        if (!isset(self::$texts[$first])) {
            $last = $first + self::BLOCK - 1;
            $codes = $first > 0xDFFF || $last < 0xD800 ? range($first, $last) : array_merge($first < 0xD800 ? range($first, 0xD7FF) : [], $last > 0xDFFF ? range(0xE000, $last) : []);
            self::$texts[$first] = $codes === [] ? '' : mb_convert_encoding(pack('N*', ...$codes), 'UTF-8', 'UTF-32BE');
        }

        return self::$texts[$first];
    }
}
