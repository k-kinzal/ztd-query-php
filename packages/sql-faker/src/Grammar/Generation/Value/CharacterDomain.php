<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Generation\Value;

use Closure;
use InvalidArgumentException;
use Override;

/**
 * Declares a bounded sequence of complete character/escape atoms with fixed delimiters.
 */
final class CharacterDomain implements ValueDomain
{
    /**
     * @var positive-int
     */
    private readonly int $lengthChoices;

    /**
     * @param non-empty-list<non-empty-string> $atoms Complete encoded characters; escapes are indivisible
     * @throws InvalidArgumentException When length bounds do not form a finite positive interval
     */
    public function __construct(
        private readonly array $atoms,
        private readonly int $minimum = 0,
        private readonly int $maximum = 255,
        private readonly string $prefix = '',
        private readonly string $suffix = '',
        private readonly int $multiple = 1,
    ) {
        $lengthChoices = $maximum - $minimum + 1;
        if ($minimum < 0 || $maximum > 65535 || $lengthChoices < 1 || $multiple < 1) {
            throw new InvalidArgumentException('Require a nonempty alphabet and ordered nonnegative length bounds.');
        }
        $this->lengthChoices = $lengthChoices;
    }

    /**
     * Chooses length and atoms in O(output length), including zero-length bodies and compound escapes.
     * @param Closure(positive-int): int $choose
     */
    #[Override]
    public function choose(Closure $choose): string
    {
        $length = ($this->minimum + $choose($this->lengthChoices)) * $this->multiple;
        $value = $this->prefix;
        for ($index = 0; $index < $length; ++$index) {
            $value .= $this->atoms[$choose(count($this->atoms))];
        }
        return $value . $this->suffix;
    }
    /**
     * Reads complete atoms using the same delimiters and repetition as construction.
     * Maximum bounds control sampling size; explicit lexical runs may be longer.
     * @return list<int>
     */
    #[Override]
    public function match(string $value, int $offset = 0): array
    {
        if (substr($value, $offset, strlen($this->prefix)) !== $this->prefix) {
            return [];
        }
        $positions = [$offset + strlen($this->prefix)];
        $ends = [];
        for ($count = 0; $positions !== []; ++$count) {
            $next = [];
            foreach ($positions as $position) {
                if ($count >= $this->minimum * $this->multiple && $count % $this->multiple === 0
                    && substr($value, $position, strlen($this->suffix)) === $this->suffix) {
                    $ends[] = $position + strlen($this->suffix);
                }
                foreach ($this->atoms as $atom) {
                    if (substr($value, $position, strlen($atom)) === $atom) {
                        $next[] = $position + strlen($atom);
                    }
                }
            }
            $positions = array_values(array_unique($next));
        }
        return array_values(array_unique($ends));
    }

}
