<?php

declare(strict_types=1);

namespace SqlParser\Compiler\Lemon;

use SqlParser\Compiler\GrammarSourceException;
use SqlParser\Grammar\Grammar;
use SqlParser\Grammar\GrammarBuilder;
use SqlParser\Grammar\GrammarException;
use SqlParser\Grammar\PrecedencePolicy;
use SqlParser\Grammar\UnknownSymbolException;

/**
 * Reads a Lemon grammar file into a grammar.
 *
 * @visibility root
 */
final class LemonReader
{
    /**
     * @param LemonScanner $scanner Splits the file into tokens
     * @param LemonDirectives $directives Reads the directives
     * @param LemonRules $rules Reads the rules
     */
    public function __construct(
        private readonly LemonScanner $scanner = new LemonScanner(),
        private readonly LemonDirectives $directives = new LemonDirectives(),
        private readonly LemonRules $rules = new LemonRules(),
    ) {
    }

    /**
     * Reads a grammar file.
     *
     * @param string $source Contents of the file
     * @param list<string> $defines Names defined for the build, as `-D` would pass them
     *
     * @return Grammar The augmented grammar it declares
     *
     * @throws GrammarSourceException When the file is not a Lemon grammar
     * @throws UnknownSymbolException When a rule names a nonterminal no rule derives
     * @throws GrammarException When the file declares no rule or a name is both a terminal and a nonterminal
     */
    public function read(string $source, array $defines = []): Grammar
    {
        $source = (new LemonPreprocessor(new LemonCondition($defines)))->process($source);
        $tokens = new LemonTokens($this->scanner->scan($source));
        $builder = new GrammarBuilder();
        $builder->policy(PrecedencePolicy::FirstRankedTerminal);
        while (($token = $tokens->peek()) !== null) {
            if ($token->is(LemonTokenKind::Directive)) {
                $tokens->next();
                $this->directives->read($token->text, $tokens, $builder);
            } else {
                $this->rules->read($tokens, $builder);
            }
        }

        return $builder->build();
    }
}
