<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Generation\Lexeme;

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
     * @param array<string, list<string>> $functions
     * @param list<string> $nonOutput
     * @param list<string> $dollarVersions
     */
    public function __construct(
        string $version,
        string $dialect,
        public readonly LexemeGenerator $lexemes,
        SpacingRule $spacing,
        public readonly array $keywords,
        public readonly array $functions = [],
        public readonly array $nonOutput = [],
        public readonly array $dollarVersions = [],
    ) {
        $this->pipeline = new ReverseLexemeGenerator($lexemes, new CandidateResolver($spacing), $version, $dialect);
    }
}
