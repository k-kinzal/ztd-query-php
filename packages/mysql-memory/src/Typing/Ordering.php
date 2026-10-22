<?php

declare(strict_types=1);

namespace MySqlMemory\Typing;

use Collator;
use MySqlMemory\Value\Encoding;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use Transliterator;

/**
 * The order of one collation: the comparison and the equality key of its strings.
 *
 * Untailored Unicode 4.0, 5.2, 9.0 and general_ci use the same weights as WEIGHT_STRING. Other Unicode
 * collations use ICU at their declared strength. Invalid UTF-8 falls back to byte comparison;
 * a multibyte character set other than UTF-8 is read in UTF-8.
 *
 * @visibility MySqlMemory
 */
final class Ordering
{
    /**
     * @var array<string, self>
     */
    private static array $orderings = [];

    /**
     * The transliterator that removes accents, created on first use.
     */
    private static ?Transliterator $accents = null;

    /**
     * @var array<string, string>|null The byte weights of the latin1 collations, read on first use
     */
    private static ?array $weights = null;

    /**
     * @param Collation $collation The collation
     * @param Collator|null $collator The ICU collator of a Unicode collation, or null for a byte or folding order
     */
    public function __construct(public readonly Collation $collation, public readonly ?Collator $collator)
    {
    }

    /**
     * Answers the shared ordering of a collation.
     */
    public static function of(Collation $collation): self
    {
        return self::$orderings[$collation->name] ??= new self($collation, self::collator($collation));
    }

    /**
     * Creates the ICU collator of a Unicode collation.
     */
    public static function collator(Collation $collation): ?Collator
    {
        if (preg_match('/\A[a-z0-9]+_(?:([a-z]{2}(?:_[a-z]{2,4})?)_)?(?:0900_(ai_ci|as_ci|as_cs)|(unicode(?:_520)?_ci))\z/', $collation->name, $match) !== 1) {
            return null;
        }
        $strength = match ($match[2] ?? '') {
            'as_ci' => Collator::SECONDARY,
            'as_cs' => Collator::TERTIARY,
            'ai_ci', '' => Collator::PRIMARY,
        };
        $collator = new Collator(($match[1] ?? '') === '' ? 'root' : $match[1]);
        $collator->setStrength($strength);

        return $collator;
    }

    /**
     * Compares two strings: negative, zero or positive.
     */
    public function compare(string $left, string $right): int
    {
        $left = $this->padded($this->unicode($left));
        $right = $this->padded($this->unicode($right));
        $leftWeight = $this->unicodeWeights($left);
        $rightWeight = $this->unicodeWeights($right);
        if ($leftWeight !== null && $rightWeight !== null) {
            if ($this->collation->padSpace) {
                $length = max(strlen($leftWeight), strlen($rightWeight));
                $padding = (new Weights())->character(' ', $this->collation);
                $leftWeight = str_pad($leftWeight, $length, $padding);
                $rightWeight = str_pad($rightWeight, $length, $padding);
            }
            return strcmp($leftWeight, $rightWeight) <=> 0;
        }
        if ($this->collator !== null && mb_check_encoding($left, 'UTF-8') && mb_check_encoding($right, 'UTF-8')) {
            $order = $this->collator->compare($left, $right);
            if ($order !== false) {
                return $order;
            }
        }
        if ($this->collation->binaryOrder() || $this->collator !== null) {
            return strcmp($left, $right) <=> 0;
        }

        return strcmp($this->folded($left), $this->folded($right)) <=> 0;
    }

    /**
     * Answers an equality key, or the hash key used by a temporary table or hash join.
     *
     * Hash keys remove source padding before weighting. Comparison keys also remove padding
     * weights before trailing ignorable characters. These differ in legacy UCA collations.
     */
    public function key(string $text, bool $hash = false): string
    {
        $text = $this->padded($this->unicode($text));
        $weight = $this->unicodeWeights($text);
        if ($weight !== null) {
            if ($this->collation->padSpace && !$hash) {
                $padding = (new Weights())->character(' ', $this->collation);
                while (str_ends_with($weight, $padding)) {
                    $weight = substr($weight, 0, -strlen($padding));
                }
            }
            return $weight;
        }
        if ($this->collator !== null && mb_check_encoding($text, 'UTF-8')) {
            $key = collator_get_sort_key($this->collator, $text);
            if ($key !== false) {
                return $key;
            }
        }

        return $this->collation->binaryOrder() || $this->collator !== null ? $text : $this->folded($text);
    }

    /**
     * Answers verified weights for an already decoded Unicode string, or null for a fallback collation or invalid input.
     */
    public function unicodeWeights(string $text): ?string
    {
        if (!mb_check_encoding($text, 'UTF-8')) {
            return null;
        }
        $unicode = new UnicodeWeights();
        $levels = $unicode->levels($this->collation->name);
        if ($levels > 0) {
            return $unicode->text($text === '' ? [] : mb_str_split($text, 1, 'UTF-8'), $levels);
        }
        if (str_ends_with($this->collation->name, '_unicode_520_ci')) {
            return (new Weights())->text($text, Collation::known('utf8mb4_unicode_520_ci'));
        }
        if (str_ends_with($this->collation->name, '_unicode_ci')) {
            return (new Weights())->text($text, Collation::known('utf8mb4_unicode_ci'));
        }
        if ((new GermanWeights())->supports($this->collation)) {
            return (new Weights())->text($text, Collation::known('utf8mb4_german2_ci'));
        }
        if ($this->collation->charset->maxLength > 1 && str_ends_with($this->collation->name, '_general_ci')) {
            return (new Weights())->text($text, Collation::known('utf8mb4_general_ci'));
        }

        return null;
    }

    /**
     * Answers a string of the collation in UTF-8 when its order reads characters of a multibyte set
     * other than UTF-8; a binary order and a single-byte set keep their bytes.
     */
    public function unicode(string $text): string
    {
        $charset = $this->collation->charset;

        return $this->collation->binaryOrder() || $charset->maxLength === 1 || Encoding::utf8($charset) ? $text : Encoding::convert($text, $charset, Charset::known('utf8mb4'));
    }

    /**
     * Removes the trailing spaces a PAD SPACE collation ignores.
     */
    public function padded(string $text): string
    {
        return $this->collation->padSpace ? rtrim($text, ' ') : $text;
    }

    /**
     * Answers the weight of each byte in the latin1 collations the server weighs byte by byte, by collation name.
     *
     * @return array<string, string>
     */
    public static function weights(): array
    {
        if (self::$weights === null) {
            /** @var array<string, string> $table */
            $table = require dirname(__DIR__, 2) . '/resources/latin1-weights.php';
            self::$weights = array_map(static fn (string $hex): string => (string) hex2bin($hex), $table);
        }

        return self::$weights;
    }

    /**
     * Answers the 256 bytes in order, which weights() maps.
     */
    public static function bytes(): string
    {
        return implode('', array_map(chr(...), range(0, 255)));
    }

    /**
     * Folds the case and the accents of Latin letters, as the general_ci collations weigh them; a
     * latin1 collation weighs each byte as the server does (verified on live 5.7.44 and 8.4.7 servers).
     */
    public function folded(string $text): string
    {
        $weights = self::weights()[$this->collation->name] ?? null;
        if ($weights !== null) {
            return strtr($text, self::bytes(), $weights);
        }
        if ($this->collation->charset->maxLength === 1 || !mb_check_encoding($text, 'UTF-8')) {
            return strtoupper($text);
        }
        if (preg_match('/[\x80-\xff]/', $text) !== 1) {
            return strtoupper($text);
        }
        self::$accents ??= Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC');
        $stripped = self::$accents === null ? false : self::$accents->transliterate($text);

        return mb_strtoupper($stripped === false ? $text : $stripped, 'UTF-8');
    }
}
