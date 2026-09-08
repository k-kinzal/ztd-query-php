<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Generation\Lexeme;

use Override;

/**
 * A declared lexical value domain with bounded default representatives and arbitrary explicit values.
 */
final class PatternLexemeGenerator implements LexemeGenerator
{
    /**
     * @param non-empty-list<string> $defaults Valid representatives when the plan does not specify a value
     */
    public function __construct(
        private readonly string $terminal,
        private readonly string $pattern,
        private readonly array $defaults,
        private readonly string $kind,
        private readonly string $definition,
    ) {
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
        $values = $input->requested === null ? $this->defaults : [$input->requested];
        $candidates = [];
        foreach ($values as $value) {
            if (preg_match($this->pattern, $value) === 1) {
                $candidates[] = new LexemeSequence([
                    new Lexeme($value, $this->kind, $input->terminal(), $this->definition),
                ], $this->definition . ':' . $value);
            }
        }
        return LexemeCandidates::of(...$candidates);
    }
}
