<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Lexeme;

use InvalidArgumentException;
use Override;
use SqlFaker\Grammar\Generation\Lexeme\IntegerLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\Lexeme;
use SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates;
use SqlFaker\Grammar\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Lexeme\LexemeSequence;
use SqlFaker\Grammar\Generation\Value\IntegerDomain;
use SqlFaker\MySql\Generation\Value\RadixDomain;

/**
 * Restricts MySQL decimal and hexadecimal forms to a source-defined nonnegative 31-bit interval.
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/sql_lex.cc
 */
final class BoundedIntegerLexemeGenerator implements LexemeGenerator
{
    /**
     * @param non-empty-list<string> $defaults
     * @throws InvalidArgumentException When the declared interval exceeds the portable 31-bit domain
     */
    public function __construct(
        private readonly string $terminal,
        private readonly int $minimum,
        private readonly int $maximum,
        private readonly array $defaults,
        private readonly string $definition,
    ) {
        if ($minimum < 0 || $maximum < $minimum || $maximum > 2147483647) {
            throw new InvalidArgumentException('Require 0 <= minimum <= maximum <= 2147483647.');
        }
    }

    /**
     * Retains the grammar's decimal and hexadecimal integer forms within the configured bounds.
     */
    #[Override]
    public function generate(LexemeInput $input): ?LexemeCandidates
    {
        if ($input->terminal()->name !== $this->terminal) {
            return null;
        }
        $value = $input->requested ?? $input->values?->value($input->index, $this->definition, new IntegerDomain((string) $this->minimum, (string) $this->maximum));
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
     * Rejects oversized hex magnitudes before conversion, including on 32-bit PHP.
     */
    public function accepts(string $spelling): bool
    {
        if ((new IntegerLexemeGenerator($this->terminal, (string) $this->minimum, (string) $this->maximum, $this->defaults, $this->definition))->accepts($spelling)) {
            return true;
        }
        $encoded = (new RadixDomain('0123456789abcdefABCDEF', '0x', ['X', 'x'], 2, 32))->digits($spelling);
        if ($encoded === null) {
            return false;
        }
        $digits = ltrim($encoded, '0');
        if (strlen($digits) > 8) {
            return false;
        }
        $value = $digits === '' ? 0 : hexdec($digits);
        return $value >= $this->minimum && $value <= $this->maximum;
    }
}
