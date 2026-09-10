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
     * A null upper bound supports scanners that classify all larger integers into another token family.
     * @param non-empty-list<string> $defaults
     */
    public function __construct(
        private readonly string $terminal,
        private readonly string $minimum,
        private readonly ?string $maximum,
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
        $value = $input->requested ?? $input->values?->value($input->index, $this->definition, new IntegerDomain($this->minimum, $this->maximum, digitSeparators: $this->digitSeparators));
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
        $domain = new IntegerDomain($this->minimum, $this->maximum, digitSeparators: $this->digitSeparators);
        return in_array(strlen($spelling), $domain->match($spelling), true);
    }
}
