<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Generation\Value;

use Closure;
use Override;

/**
 * Prefixed and quoted radix literals. Whole-byte sampling is a charset constraint;
 * the same lexical declaration reads ordinary and explicitly requested bytes.
 */
final class RadixDomain implements ValueDomain
{
    /**
     * @param non-empty-string $digits
     * @param non-empty-string $prefix
     * @param non-empty-list<string> $quotedPrefixes
     */
    public function __construct(
        private readonly string $digits,
        private readonly string $prefix,
        private readonly array $quotedPrefixes,
        private readonly int $quotedMultiple,
        private readonly int $maximum,
        private readonly bool $ascii = false,
    ) {
    }

    /**
     * Constructs whole ASCII bytes for an introducer and arbitrary radix digits otherwise.
     * @param Closure(positive-int): int $choose
     */
    #[Override]
    public function choose(Closure $choose): string
    {
        $quoted = $choose(2) === 1;
        $hex = strlen($this->digits) > 2;
        $atoms = $this->ascii
            ? array_map(static fn (int $byte): string => sprintf($hex ? '%02x' : '%08b', $byte), range(0, 127))
            : str_split($this->digits);
        $unit = $this->ascii ? ($hex ? 2 : 8) : 1;
        $maximum = intdiv($this->maximum, $unit);
        $multiple = $quoted && !$this->ascii ? $this->quotedMultiple : 1;
        return (new CharacterDomain($atoms, $quoted ? 0 : 1, intdiv($maximum, $multiple), $quoted ? $this->quotedPrefixes[0] . "'" : $this->prefix, $quoted ? "'" : '', $multiple))->choose($choose);
    }

    /**
     * Reads complete prefixed runs or a quoted run closed by its first delimiter.
     * @return list<int>
     */
    #[Override]
    public function match(string $value, int $offset = 0): array
    {
        if (substr($value, $offset, strlen($this->prefix)) === $this->prefix) {
            return (new CharacterDomain(str_split($this->digits), 1, $this->maximum, $this->prefix))->match($value, $offset);
        }
        return (new SequenceDomain(new WordDomain($this->quotedPrefixes), new CharacterDomain(str_split($this->digits), 0, $this->maximum, "'", "'", $this->quotedMultiple)))->match($value, $offset);
    }

    /**
     * Returns the encoded magnitude only after the complete spelling matches this declaration.
     */
    public function digits(string $value): ?string
    {
        if (!in_array(strlen($value), $this->match($value), true)) {
            return null;
        }
        if (str_starts_with($value, $this->prefix)) {
            return substr($value, strlen($this->prefix));
        }
        foreach ($this->quotedPrefixes as $prefix) {
            if (str_starts_with($value, $prefix . "'")) {
                return substr($value, strlen($prefix) + 1, -1);
            }
        }
        return null;
    }
}
