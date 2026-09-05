<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Grammar;

use SqlFaker\Compiler\Bison\Ast\BisonAst;
use SqlFaker\Grammar\UnknownSymbolException;

/**
 * Compiles a Grammar from a BisonAst.
 *
 * This class transforms the Bison AST representation into a formal grammar
 * structure suitable for SQL generation.
 */
final class GrammarCompiler
{
    /**
     * Compile a BisonAst into a Grammar.
     *
     * Extracts production rules from the AST, determining terminal/non-terminal
     * status for each symbol based on rule and declaration tables.
     *
     * @throws UnknownSymbolException When a production names a symbol the grammar never declares
     */
    public function compile(BisonAst $ast): Grammar
    {
        $grammar = (new \SqlFaker\Compiler\Bison\GrammarCompiler())->compile($ast);

        return new Grammar($grammar->startSymbol, $grammar->ruleMap);
    }
}
