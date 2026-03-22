<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite;

use SqlFaker\Contract\Grammar;
use SqlFaker\Contract\SnapshotCompiler as SnapshotCompilerContract;
use SqlFaker\Grammar\ContractGrammarProjector;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Sqlite\Lemon\LemonParser;

final class SnapshotCompiler implements SnapshotCompilerContract
{
    public function __construct(
        private readonly LemonParser $parser = new LemonParser(),
    ) {
    }

    public function compile(string $source): Grammar
    {
        return ContractGrammarProjector::project(
            $this->parser->parse($source),
            NonTerminal::class,
        );
    }
}
