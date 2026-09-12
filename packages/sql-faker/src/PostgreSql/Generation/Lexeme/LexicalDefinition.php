<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation\Lexeme;

use SqlFaker\Grammar\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Grammar\Generation\Output\CandidateResolver;
use SqlFaker\Grammar\Generation\Output\ReverseLexemeGenerator;
use SqlFaker\Grammar\Generation\Spacing\SpacingRule;

/**
 * Carries one lexical composition and its tokenizer and token-budget metadata.
 */
final class LexicalDefinition
{
    /**
     * Realizes the declared lexemes with their spacing rules for the selected release.
     */
    public readonly ReverseLexemeGenerator $pipeline;

    /**
     * @param array<string, list<string>> $keywords
     * @param list<string> $nonOutput
     */
    public function __construct(
        string $version,
        string $dialect,
        public readonly LexemeGenerator $lexemes,
        SpacingRule $spacing,
        public readonly array $keywords,
        public readonly array $nonOutput = [],
    ) {
        $this->pipeline = new ReverseLexemeGenerator($lexemes, new CandidateResolver($spacing), $version, $dialect);
    }
}
