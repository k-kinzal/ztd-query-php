<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite;

use SqlFaker\Contract\Grammar;
use SqlFaker\Contract\RewriteProgram;
use SqlFaker\Contract\SupportedGrammarBuilder as SupportedGrammarBuilderContract;

final class SupportedGrammarBuilder implements SupportedGrammarBuilderContract
{
    public function __construct(
        private readonly SupportedGrammarCompiler $compiler = new SupportedGrammarCompiler(),
    ) {
    }

    public function build(Grammar $snapshot): Grammar
    {
        return $this->compiler->compile($snapshot);
    }

    public function rewriteProgram(): RewriteProgram
    {
        return $this->compiler->rewriteProgram();
    }
}
