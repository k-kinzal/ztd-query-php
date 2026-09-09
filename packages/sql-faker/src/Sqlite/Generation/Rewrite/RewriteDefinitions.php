<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite\Generation\Rewrite;

use SqlFaker\Grammar\Generation\Token\ExpressionGroupingRule;
use SqlFaker\Grammar\Generation\Token\TerminalMappingRule;
use SqlFaker\Grammar\Generation\Token\TokenRewriter;

/**
 * Declares SQLite parser conditions without removing their original grammar alternatives.
 */
final class RewriteDefinitions
{
    /**
     * Runs source-scoped transformations before spelling and spacing.
     */
    public function create(): TokenRewriter
    {
        return new TokenRewriter(new FunctionArgumentRule(), new GeneratedColumnRule(), new TerminalMappingRule('generated', 'ID', 'GENERATED_STORAGE', 'src/build.c:sqlite3AddGenerated'), new CompoundSelectRule(), new UpsertSourceRule(), new TableOptionRule(), new StrictTableRule(), new WithoutRowidRule(), new IdentifierListRule(), new JoinRule(), new WindowFrameRule(), new ExpressionGroupingRule(['expr'], 'parse.y:expr:parenthesized-operands', 'LP', 'RP'));
    }
}
