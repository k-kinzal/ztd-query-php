<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\View;

/**
 * The command a rule rewrites.
 *
 * Mirrors the `CmdType` of `RuleStmt`.
 * Source: https://www.postgresql.org/docs/17/sql-createrule.html.
 *
 * @visibility SqlSemantics
 */
enum RuleEvent: string
{
    case Select = 'SELECT';
    case Insert = 'INSERT';
    case Update = 'UPDATE';
    case Delete = 'DELETE';
}
