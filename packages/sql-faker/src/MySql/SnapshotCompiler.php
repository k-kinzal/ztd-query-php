<?php

declare(strict_types=1);

namespace SqlFaker\MySql;

use SqlFaker\Contract\Grammar;
use SqlFaker\Contract\SnapshotCompiler as SnapshotCompilerContract;
use SqlFaker\Grammar\ContractGrammarProjector;
use SqlFaker\MySql\Bison\BisonParser;
use SqlFaker\MySql\Grammar\GrammarCompiler;
use SqlFaker\MySql\Grammar\NonTerminal;

final class SnapshotCompiler implements SnapshotCompilerContract
{
    public function __construct(
        private readonly BisonParser $parser = new BisonParser(),
        private readonly GrammarCompiler $compiler = new GrammarCompiler(),
    ) {
    }

    public function compile(string $source): Grammar
    {
        return ContractGrammarProjector::project(
            $this->compiler->compile($this->parser->parse($source)),
            NonTerminal::class,
        );
    }
}
