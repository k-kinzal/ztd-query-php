<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Generation\Lexeme;

use InvalidArgumentException;
use Override;
use SqlFaker\Grammar\Generation\Value\ValueDomain;

/**
 * Constructs lexical values from a domain; only caller-supplied spellings need recognition.
 */
final class ValueLexemeGenerator implements LexemeGenerator
{
    /**
     * @param non-empty-list<string> $defaults Valid representatives when the plan does not specify a value
     * @throws InvalidArgumentException When a representative does not belong to the declaration
     */
    public function __construct(
        private readonly string $terminal,
        private readonly ValueDomain $domain,
        private readonly array $defaults,
        private readonly string $kind,
        private readonly string $definition,
    ) {
        foreach ($defaults as $value) {
            if (!in_array(strlen($value), $domain->match($value), true)) {
                throw new InvalidArgumentException('Representative outside lexical domain: ' . $terminal);
            }
        }
    }

    /**
     * Recognizes its terminal and checks explicit values against the declared domain.
     */
    #[Override]
    public function generate(LexemeInput $input): ?LexemeCandidates
    {
        if ($input->terminal()->name !== $this->terminal) {
            return null;
        }
        $requested = $input->requested ?? $input->values?->value($input->index, $this->definition, $this->domain);
        $values = $requested === null ? $this->defaults : [$requested];
        $candidates = [];
        foreach ($values as $value) {
            if ($input->requested === null || in_array(strlen($value), $this->domain->match($value), true)) {
                $candidates[] = new LexemeSequence([
                    new Lexeme($value, $this->kind, $input->terminal(), $this->definition),
                ], $this->definition . ':' . $value);
            }
        }
        return LexemeCandidates::of(...$candidates);
    }
}
