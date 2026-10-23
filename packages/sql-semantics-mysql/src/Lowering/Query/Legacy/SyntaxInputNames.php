<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Query\Legacy;

use SqlParser\Parser\Node;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Rules\Dml\Update\Structure;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;

/**
 * Retains MySQL 5.6 input-name conditions before later malformed query clauses.
 *
 * The completed relation list uses the ordinary semantic collision rule. Catalog
 * and default-database precedence remain with the consuming input adapter.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SyntaxInputNames
{
    /**
     * Limits incomplete-reduction precedence to inputs that did not parse.
     * Complete inputs retain the full query and stored-program ordering rules.
     */
    public function unparsed(\SqlParser\Parser\ReductionParser $parser, string $statement, \SqlSemantics\Contract\LanguageProfile $profile): bool
    {
        if ($profile->grammar !== GrammarRelease::MySql5651) {
            return false;
        }
        try {
            $parser->parse($statement);
        } catch (\SqlParser\Lexer\SourceException) {
            return true;
        }
        return false;
    }

    /**
     * Answers a fixed repeated name when the legacy table list has completed.
     */
    public function completed(Node $node, Lowering $lowering): ?ParseFailure
    {
        if ($lowering->profile->grammar !== GrammarRelease::MySql5651 || $node->name !== 'join_table_list') {
            return null;
        }
        $problem = (new Structure())->names($lowering->queries->tables($node));
        return $problem === null ? null : new ParseFailure($problem, true);
    }
}
