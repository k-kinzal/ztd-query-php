<?php

declare(strict_types=1);

namespace SqlParser\Compiler\Bison;

use SqlParser\Compiler\GrammarSourceException;
use SqlParser\Grammar\Grammar;
use SqlParser\Grammar\GrammarBuilder;
use SqlParser\Grammar\GrammarException;
use SqlParser\Grammar\PrecedencePolicy;
use SqlParser\Grammar\UnknownSymbolException;

/**
 * Reads a Bison grammar file into a grammar.
 *
 * @visibility root
 */
final class BisonReader
{
    /**
     * @param BisonScanner $scanner Splits the file into tokens
     * @param BisonDeclarations $declarations Reads the declarations section
     * @param BisonRules $rules Reads the rules section
     */
    public function __construct(
        private readonly BisonScanner $scanner = new BisonScanner(),
        private readonly BisonDeclarations $declarations = new BisonDeclarations(),
        private readonly BisonRules $rules = new BisonRules(),
    ) {
    }

    /**
     * Reads a grammar file.
     *
     * @param string $source Contents of the file
     *
     * @return Grammar The augmented grammar it declares
     *
     * @throws GrammarSourceException When the file is not a Bison grammar
     * @throws UnknownSymbolException When a rule names a symbol the file never declares
     * @throws GrammarException When the file declares no rule or a name is both a terminal and a nonterminal
     */
    public function read(string $source): Grammar
    {
        $tokens = new BisonTokens($this->scanner->scan($source));
        $builder = new GrammarBuilder();
        $builder->policy(PrecedencePolicy::LastTerminal);
        $builder->terminal('error');
        $this->declarations->read($tokens, $builder);
        $this->rules->read($tokens, $builder);

        return $builder->build();
    }
}
