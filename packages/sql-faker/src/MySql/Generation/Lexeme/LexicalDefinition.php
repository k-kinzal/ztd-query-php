<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Lexeme;

use SqlFaker\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Generation\Lexeme\SpacingRule;
use SqlFaker\Generation\Output\CandidateResolver;
use SqlFaker\Generation\Output\ReverseLexemeGenerator;

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
