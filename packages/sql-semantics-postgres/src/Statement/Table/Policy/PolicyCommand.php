<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Policy;

/**
 * The commands a row security policy applies to.
 *
 * Mirrors the `cmd_name` of `CreatePolicyStmt`. ALL is the default and is kept when written.
 * Source: https://www.postgresql.org/docs/17/sql-createpolicy.html.
 *
 * @visibility public
 * @example Reading the command of a policy
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE POLICY p ON t FOR SELECT USING (true)')->statement->command // => \SqlSemantics\Platform\PostgreSql\Statement\Table\Policy\PolicyCommand::Select
 */
enum PolicyCommand: string
{
    case All = 'ALL';
    case Select = 'SELECT';
    case Insert = 'INSERT';
    case Update = 'UPDATE';
    case Delete = 'DELETE';
}
