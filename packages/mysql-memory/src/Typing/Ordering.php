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
 * The Unicode collations use the root collator of ICU at the strength of the collation; a
 * string that is not valid UTF-8 is compared by its bytes. A string of a multibyte character set
 * other than UTF-8 is read in UTF-8.
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
     * Answers a key that is equal for two strings exactly when they compare equal.
     */
    public function key(string $text): string
    {
        $text = $this->padded($this->unicode($text));
        if ($this->collator !== null && mb_check_encoding($text, 'UTF-8')) {
            $key = collator_get_sort_key($this->collator, $text);
            if ($key !== false) {
                return $key;
            }
        }

        return $this->collation->binaryOrder() || $this->collator !== null ? $text : $this->folded($text);
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
