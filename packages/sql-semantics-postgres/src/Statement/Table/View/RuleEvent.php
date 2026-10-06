<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\View;

/**
 * The command a rule rewrites.
 *
 * Mirrors the `CmdType` of `RuleStmt`.
 * Source: https://www.postgresql.org/docs/17/sql-createrule.html.
 *
 * @visibility public
 * @example Reading the event of a rule
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE RULE r AS ON DELETE TO t DO INSTEAD NOTHING')->statement->event // => \SqlSemantics\Platform\PostgreSql\Statement\Table\View\RuleEvent::Delete
 */
enum RuleEvent: string
{
    case Select = 'SELECT';
    case Insert = 'INSERT';
    case Update = 'UPDATE';
    case Delete = 'DELETE';
}
