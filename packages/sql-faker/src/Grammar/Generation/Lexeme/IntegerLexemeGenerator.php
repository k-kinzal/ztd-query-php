<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Generation\Lexeme;

use Override;
use SqlFaker\Grammar\Generation\Value\IntegerDomain;

/**
 * An unsigned decimal scanner domain whose inclusive bounds do not depend on PHP's integer width.
 */
final class IntegerLexemeGenerator implements LexemeGenerator
{
    /**
     * Binds the source-defined magnitude interval and bounded default representatives.
     * @param non-empty-list<string> $defaults
     */
    public function __construct(
        private readonly string $terminal,
        private readonly string $minimum,
        private readonly string $maximum,
        private readonly array $defaults,
        private readonly string $definition,
        private readonly bool $digitSeparators = false,
    ) {
    }

    /**
     * Preserves valid explicit spellings, including leading zeroes, without narrowing through a PHP integer cast.
     */
    #[Override]
    public function generate(LexemeInput $input): ?LexemeCandidates
    {
        if ($input->terminal()->name !== $this->terminal) {
            return null;
        }
        $value = $input->requested ?? $input->values?->value($input->index, $this->definition, new IntegerDomain($this->minimum, $this->maximum));
        $candidates = [];
        foreach ($value === null ? $this->defaults : [$value] as $spelling) {
            if (!$this->accepts($spelling)) {
                continue;
            }
            $candidates[] = new LexemeSequence([
                new Lexeme($spelling, 'number', $input->terminal(), $this->definition),
            ], $this->definition . ':' . $spelling);
        }
        return LexemeCandidates::of(...$candidates);
    }

    /**
     * Compares decimal magnitudes by significant digit count, then lexicographically at equal length.
     */
    public function accepts(string $spelling): bool
    {
        $pattern = $this->digitSeparators ? '/\A[0-9]+(?:_[0-9]+)*\z/D' : '/\A[0-9]+\z/D';
        if (preg_match($pattern, $spelling) !== 1) {
            return false;
        }
        $significant = ltrim(str_replace('_', '', $spelling), '0');
        $value = $significant === '' ? '0' : $significant;
        $minimumLength = strlen($this->minimum);
        $maximumLength = strlen($this->maximum);
        $length = strlen($value);
        return ($length > $minimumLength || ($length === $minimumLength && strcmp($value, $this->minimum) >= 0))
            && ($length < $maximumLength || ($length === $maximumLength && strcmp($value, $this->maximum) <= 0));
    }
}
