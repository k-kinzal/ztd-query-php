<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Routine\Program;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Routine\Stored\ProgramKind;
use SqlSemantics\Model\ResultStatement;
use SqlSemantics\Model\Statement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Diagnoses statements MySQL rejects inside stored programs, and those stored functions and triggers cannot run.
 * @visibility SqlSemantics
 */
final class Placement
{
    /**
     * Rejects nested routine and trigger definitions and nested event bodies before binding them.
     * @throws InvalidSql
     */
    public static function nested(Node $statement): void
    {
        $nested = Tree::outer($statement, ['sp_tail', 'sf_tail', 'trigger_tail', 'ev_sql_stmt']);
        if ($nested !== []) {
            throw new InvalidSql(InputViolation::ProgramStatement, $nested[0]);
        }
    }

    /**
     * Rejects statements no stored program may contain, and, in functions and triggers, result sets, commits, FLUSH, RESET and prepared statements.
     * @throws InvalidSql
     */
    public static function check(BoundStatement $bound, Node $statement, ProgramFrame $frame): void
    {
        $legacy = in_array($frame->context->tables->schema->grammarVersion, ['mysql-5.6.51', 'mysql-5.7.44'], true);
        $forbidden = $bound instanceof Statement\Definition\MySql\DropProcedureStatement
            || $bound instanceof Statement\Definition\MySql\DropFunctionStatement
            || $bound instanceof Statement\Definition\MySql\View\AlterViewStatement
            || $bound instanceof Statement\Locking\LockTablesStatement
            || $bound instanceof Statement\Server\UnlockTablesStatement
            || $bound instanceof Statement\Loading\LoadFileStatement
            || $bound instanceof Statement\Procedural\HelpStatement
            || $bound->kind === StatementKind::Use
            || ($legacy && ($bound instanceof Statement\Maintenance\MySql\CheckTablesStatement || $bound instanceof Statement\Definition\MySql\DropDatabaseStatement || str_starts_with($bound::class, 'SqlSemantics\\Model\\Statement\\Cursor\\Handler\\')));
        if ($forbidden || (in_array($frame->kind, [ProgramKind::Function, ProgramKind::Trigger], true) && self::functionForbidden($bound, $statement))) {
            throw new InvalidSql(InputViolation::ProgramStatement, $statement);
        }
    }

    /**
     * Reports statements that return a result set, commit implicitly or explicitly, flush, reset or prepare SQL.
     */
    public static function functionForbidden(BoundStatement $bound, Node $statement): bool
    {
        if ($bound instanceof ResultStatement && $bound->resultColumns() !== []) {
            return true;
        }
        $tokens = $statement->tokens();
        if (in_array(strtoupper($tokens[0]->text ?? ''), ['CREATE', 'DROP'], true) && strtoupper($tokens[1]->text ?? '') === 'TEMPORARY') {
            return false;
        }
        if ($bound instanceof Statement\Transaction\RollbackToSavepointStatement) {
            return false;
        }
        return in_array($bound->kind, [
            StatementKind::Create, StatementKind::Alter, StatementKind::Drop, StatementKind::Rename, StatementKind::Truncate,
            StatementKind::Grant, StatementKind::Revoke, StatementKind::Begin, StatementKind::Commit, StatementKind::Rollback,
            StatementKind::Lock, StatementKind::Unlock, StatementKind::Load, StatementKind::Install, StatementKind::Uninstall,
            StatementKind::Cache, StatementKind::Flush, StatementKind::Reset, StatementKind::Prepare, StatementKind::Execute,
            StatementKind::Deallocate, StatementKind::Explain, StatementKind::Show, StatementKind::Analyze, StatementKind::Optimize,
            StatementKind::Repair, StatementKind::Check, StatementKind::Checksum,
        ], true);
    }
}
