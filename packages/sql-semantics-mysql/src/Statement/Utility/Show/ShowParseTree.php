<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Show;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityMisuse;
use SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityRule;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * SHOW PARSE_TREE: the parse tree of a statement, in debug builds of the server (MySQL 8.1 and later).
 *
 * Rule: MYSQL-SHOW-PARSE-TREE-001. The grammar reads the statement; a
 * release build of the server refuses it as a syntax error, so the
 * statement is a diagnostic (UtilityRule::DebugOnly) and its rows are not
 * modeled. The wrapped statement is inspected, not run: its parts receive
 * their facts, its rows and declarations are discarded. Terminates: the
 * wrapped statement is a strict subtree.
 * Source: the `show_parse_tree_stmt` rule of sql/sql_yacc.yy (8.1), checked
 * against a release build of 8.4.7. Status: Implemented.
 *
 * @visibility public
 * @example Reading the inspected statement
 *     $show = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SHOW PARSE_TREE SELECT 1');
 *     [$show->facts->diagnostics[0]->rule, $show->shape(), $show->toString()] // => [\SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityRule::DebugOnly, null, 'SHOW PARSE_TREE SELECT 1']
 */
final class ShowParseTree implements Statement
{
    use Snapshot;

    /**
     * @param Statement $statement The statement whose parse tree is requested
     */
    public function __construct(public readonly Statement $statement)
    {
    }

    /**
     * Inspects the statement and reports that a release build refuses the request.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $derivation->inspected($this->statement);
        $derivation->report(new UtilityMisuse(UtilityRule::DebugOnly));
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('SHOW', 'PARSE_TREE')->node($this->statement);
    }
}
