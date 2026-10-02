<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Dispatch;

/**
 * Names the family that lowers each alternative of the rules `create`, `alter` and `drop`.
 *
 * Rule: MYSQL-DEFINITION-ROUTES-001. Scope: create (every release), alter
 * and drop (5.6, 5.7). These rules gather statements of several families.
 * An alternative is routed by the whole symbols it starts with; the family it is
 * routed to claims the production and reads its symbols. The first matching
 * row decides. `CREATE view_or_trigger_or_sp_or_event` belongs to the
 * dispatch family itself, which splits it by the kind of object. The rows
 * cover every alternative of every shipped release; the unit test of this
 * class checks that against the production lists. Terminates: one pass over
 * the rows. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class DefinitionRoutes
{
    /**
     * The routing rows: the rule, the symbols an alternative starts with, and the family.
     */
    public const ROUTES = [
        ['create', 'CREATE opt_table_options TABLE_SYM', Family::TableDefinition],
        ['create', 'CREATE opt_unique INDEX_SYM', Family::TableDefinition],
        ['create', 'CREATE fulltext INDEX_SYM', Family::TableDefinition],
        ['create', 'CREATE spatial INDEX_SYM', Family::TableDefinition],
        ['create', 'CREATE DATABASE', Family::Server],
        ['create', 'CREATE view_or_trigger_or_sp_or_event', Family::Definition],
        ['create', 'CREATE USER', Family::Account],
        ['create', 'CREATE LOGFILE_SYM', Family::Server],
        ['create', 'CREATE TABLESPACE', Family::Server],
        ['create', 'CREATE TABLESPACE_SYM', Family::Server],
        ['create', 'CREATE UNDO_SYM', Family::Server],
        ['create', 'CREATE server_def', Family::Server],
        ['create', 'CREATE SERVER_SYM', Family::Server],
        ['alter', 'ALTER opt_ignore TABLE_SYM', Family::TableChange],
        ['alter', 'ALTER TABLE_SYM', Family::TableChange],
        ['alter', 'ALTER DATABASE', Family::Server],
        ['alter', 'ALTER PROCEDURE_SYM', Family::Routine],
        ['alter', 'ALTER FUNCTION_SYM', Family::Routine],
        ['alter', 'ALTER view_algorithm', Family::TableDefinition],
        ['alter', 'ALTER definer_opt view_tail', Family::TableDefinition],
        ['alter', 'ALTER definer_opt EVENT_SYM', Family::Routine],
        ['alter', 'ALTER TABLESPACE', Family::Server],
        ['alter', 'ALTER TABLESPACE_SYM', Family::Server],
        ['alter', 'ALTER LOGFILE_SYM', Family::Server],
        ['alter', 'ALTER SERVER_SYM', Family::Server],
        ['alter', 'ALTER USER', Family::Account],
        ['alter', 'alter_user_command', Family::Account],
        ['alter', 'alter_instance_stmt', Family::Server],
        ['drop', 'DROP opt_temporary', Family::TableChange],
        ['drop', 'DROP INDEX_SYM', Family::TableChange],
        ['drop', 'DROP DATABASE', Family::Server],
        ['drop', 'DROP FUNCTION_SYM', Family::Routine],
        ['drop', 'DROP PROCEDURE_SYM', Family::Routine],
        ['drop', 'DROP USER', Family::Account],
        ['drop', 'DROP VIEW_SYM', Family::TableDefinition],
        ['drop', 'DROP EVENT_SYM', Family::Routine],
        ['drop', 'DROP TRIGGER_SYM', Family::Routine],
        ['drop', 'DROP TABLESPACE', Family::Server],
        ['drop', 'DROP TABLESPACE_SYM', Family::Server],
        ['drop', 'DROP LOGFILE_SYM', Family::Server],
        ['drop', 'DROP SERVER_SYM', Family::Server],
    ];

    /**
     * Answers the family of a `create`, `alter` or `drop` production, or null when no row matches.
     */
    public function family(string $signature): ?Family
    {
        foreach (self::ROUTES as [$rule, $symbols, $family]) {
            if (str_starts_with($signature . ' ', $rule . ': ' . $symbols . ' ')) {
                return $family;
            }
        }

        return null;
    }
}
