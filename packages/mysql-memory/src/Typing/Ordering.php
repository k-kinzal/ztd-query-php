<?php

declare(strict_types=1);

namespace MySqlMemory\Typing;

use Collator;

/**
 * The order of one collation: the comparison and the equality key of its strings.
 *
 * The Unicode collations use the root collator of ICU at the strength of the collation; a
 * string that is not valid UTF-8 is compared by its bytes.
 *
 * @visibility MySqlMemory\Typing
 */
final class Ordering
{
    /**
     * @var array<string, self>
     */
    private static array $orderings = [];

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
        return self::$orderings[$collation->value] ??= new self($collation, self::collator($collation));
    }

    /**
     * Creates the ICU collator of a Unicode collation.
     */
    public static function collator(Collation $collation): ?Collator
    {
        $strength = match ($collation) {
            Collation::Utf8mb40900AiCi, Collation::Utf8mb4UnicodeCi => Collator::PRIMARY,
            Collation::Utf8mb40900AsCi => Collator::SECONDARY,
            Collation::Utf8mb40900AsCs => Collator::TERTIARY,
            default => null,
        };
        if ($strength === null) {
            return null;
        }
        $collator = new Collator('root');
        $collator->setStrength($strength);

        return $collator;
    }

    /**
     * Compares two strings: negative, zero or positive.
     */
    public function compare(string $left, string $right): int
    {
        $left = $this->padded($left);
        $right = $this->padded($right);
        if ($this->collator !== null && mb_check_encoding($left, 'UTF-8') && mb_check_encoding($right, 'UTF-8')) {
            $order = $this->collator->compare($left, $right);
            if ($order !== false) {
                return $order;
            }
        }
        if ($this->collation->binary() || $this->collator !== null) {
            return $left <=> $right;
        }

        return $this->folded($left) <=> $this->folded($right);
    }

    /**
     * Answers a key that is equal for two strings exactly when they compare equal.
     */
    public function key(string $text): string
    {
        $text = $this->padded($text);
        if ($this->collator !== null && mb_check_encoding($text, 'UTF-8')) {
            $key = $this->collator->getSortKey($text);
            if ($key !== false) {
                return $key;
            }
        }

        return $this->collation->binary() || $this->collator !== null ? $text : $this->folded($text);
    }

    /**
     * Removes the trailing spaces a PAD SPACE collation ignores.
     */
    public function padded(string $text): string
    {
        return $this->collation->padSpace() ? rtrim($text, ' ') : $text;
    }

    /**
     * Folds the case and the accents of Latin letters, as the general_ci collations weigh them.
     */
    public function folded(string $text): string
    {
        if ($this->collation->charset()->maxLength() === 1 || !mb_check_encoding($text, 'UTF-8')) {
            return strtoupper($text);
        }
        $stripped = transliterator_transliterate('NFD; [:Nonspacing Mark:] Remove; NFC', $text);

        return mb_strtoupper($stripped === false ? $text : $stripped, 'UTF-8');
    }
}
