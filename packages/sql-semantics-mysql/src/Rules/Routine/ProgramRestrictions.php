<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Routine;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadFormat;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadTable;
use SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Deallocate;
use SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Execute;
use SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Prepare;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\QueryStatement;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\Set\LeadingUnion;
use SqlSemantics\Platform\MySql\Statement\Query\Set\OrderedSetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\Reset;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetPersist;
use SqlSemantics\Platform\MySql\Statement\Routine\AlterEvent;
use SqlSemantics\Platform\MySql\Statement\Routine\AlterRoutine;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateEvent;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateTrigger;
use SqlSemantics\Platform\MySql\Statement\Routine\DropProgram;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;
use SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind;
use SqlSemantics\Platform\MySql\Statement\Server\Flush\Flush;
use SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushTables;
use SqlSemantics\Platform\MySql\Statement\Server\Lock\LockTables;
use SqlSemantics\Platform\MySql\Statement\Server\Lock\UnlockTables;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\AnalyzeTable;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\ChecksumTable;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\CheckTable;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\OptimizeTable;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\RepairTable;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Commit;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Rollback;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\StartTransaction;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\DescribeTable;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainConnection;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\Help;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\UseDatabase;
use SqlSemantics\Platform\MySql\Statement\View\AlterView;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Statement;

/**
 * Reports the statements a stored program may not contain.
 *
 * Rule: MYSQL-PROGRAM-RESTRICTIONS-001. No stored program contains LOCK
 * TABLES, UNLOCK TABLES, ALTER VIEW, LOAD DATA, LOAD XML or USE
 * (ER_SP_BADSTATEMENT), a CREATE PROCEDURE, CREATE FUNCTION of a stored
 * function or CREATE TRIGGER (ER_SP_NO_RECURSIVE_CREATE), an ALTER or DROP
 * of a procedure or function (ER_SP_NO_DROP_SP), or a CREATE EVENT or an
 * ALTER EVENT with a body (ER_EVENT_RECURSION_FORBIDDEN). A stored function
 * or trigger in addition returns no result set: no query without INTO, no
 * SHOW, EXPLAIN, DESCRIBE, HELP and no CHECK, ANALYZE, OPTIMIZE, REPAIR or
 * CHECKSUM TABLE (ER_SP_NO_RETSET); performs no explicit commit or rollback
 * (ER_COMMIT_NOT_ALLOWED_IN_SF_OR_TRG); and uses no PREPARE, EXECUTE or
 * DEALLOCATE PREPARE, no FLUSH and no RESET, RESET PERSIST included, which
 * the grammar reads as the same RESET command (rule `reset` of
 * sql_yacc.yy) (ER_STMT_NOT_ALLOWED_IN_SF_OR_TRG). Limit: the implicit commit of a data
 * definition statement in a stored function or trigger is not reported.
 * Terminates: the INTO search descends into strict parts of a query.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/stored-program-restrictions.html,
 * https://dev.mysql.com/doc/refman/8.4/en/create-procedure.html ("The USE
 * statement is not permitted in stored routines").
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ProgramRestrictions
{
    /**
     * Reports a statement of a stored program of a kind that may not contain it.
     */
    public function check(Statement $statement, ProgramKind $kind, Derivation $derivation): void
    {
        $problem = $this->anywhere($statement);
        if ($problem === null && ($kind === ProgramKind::Function || $kind === ProgramKind::Trigger)) {
            $problem = $this->function($statement, $kind);
        }
        if ($problem !== null) {
            $derivation->report($problem);
        }
    }

    /**
     * Answers the problem of a statement no stored program may contain.
     */
    public function anywhere(Statement $statement): ?ProgramProblem
    {
        return match (true) {
            $statement instanceof LockTables => new ProgramProblem(ProgramRule::BadStatement, 'LOCK'),
            $statement instanceof UnlockTables => new ProgramProblem(ProgramRule::BadStatement, 'UNLOCK'),
            $statement instanceof AlterView => new ProgramProblem(ProgramRule::BadStatement, 'ALTER VIEW'),
            $statement instanceof LoadTable => new ProgramProblem(ProgramRule::BadStatement, $statement->format === LoadFormat::Xml ? 'LOAD XML' : 'LOAD DATA'),
            $statement instanceof UseDatabase => new ProgramProblem(ProgramRule::BadStatement, 'USE'),
            $statement instanceof CreateProcedure => new ProgramProblem(ProgramRule::RecursiveCreate, ProgramKind::Procedure->value),
            $statement instanceof CreateFunction => new ProgramProblem(ProgramRule::RecursiveCreate, ProgramKind::Function->value),
            $statement instanceof CreateTrigger => new ProgramProblem(ProgramRule::RecursiveCreate, ProgramKind::Trigger->value),
            $statement instanceof CreateEvent, $statement instanceof AlterEvent && $statement->body !== null => new ProgramProblem(ProgramRule::EventRecursion),
            $statement instanceof AlterRoutine => new ProgramProblem(ProgramRule::NestedAlterOrDrop, $statement->kind->value),
            $statement instanceof DropProgram && ($statement->kind === ProgramKind::Procedure || $statement->kind === ProgramKind::Function) => new ProgramProblem(ProgramRule::NestedAlterOrDrop, $statement->kind->value),
            default => null,
        };
    }

    /**
     * Answers the problem of a statement a stored function or trigger may not contain.
     */
    public function function(Statement $statement, ProgramKind $kind): ?ProgramProblem
    {
        return match (true) {
            $this->results($statement) => new ProgramProblem(ProgramRule::ResultSet, strtolower($kind->value)),
            $statement instanceof Commit, $statement instanceof Rollback, $statement instanceof StartTransaction => new ProgramProblem(ProgramRule::CommitInFunction),
            $statement instanceof Prepare, $statement instanceof Execute, $statement instanceof Deallocate => new ProgramProblem(ProgramRule::FunctionStatement, 'Dynamic SQL'),
            $statement instanceof Flush, $statement instanceof FlushTables => new ProgramProblem(ProgramRule::FunctionStatement, 'FLUSH'),
            $statement instanceof Reset, $statement instanceof ResetPersist => new ProgramProblem(ProgramRule::FunctionStatement, 'RESET'),
            default => null,
        };
    }

    /**
     * Tells whether a statement returns a result set to the client.
     */
    public function results(Statement $statement): bool
    {
        if ($statement instanceof Query) {
            return !$this->into($statement);
        }

        return $statement instanceof Explain || $statement instanceof ExplainConnection || $statement instanceof DescribeTable || $statement instanceof Help
            || $statement instanceof CheckTable || $statement instanceof AnalyzeTable || $statement instanceof OptimizeTable || $statement instanceof RepairTable || $statement instanceof ChecksumTable
            || str_starts_with($statement::class, 'SqlSemantics\\Platform\\MySql\\Statement\\Utility\\Show\\');
    }

    /**
     * Tells whether a query writes its rows INTO variables or a file instead of returning them.
     */
    public function into(Query|LeadingUnion $query): bool
    {
        return match (true) {
            $query instanceof Select => $query->into !== null,
            $query instanceof QueryStatement => $query->into !== null || $this->into($query->query),
            $query instanceof QueryExpression => $this->into($query->body),
            $query instanceof ParenthesizedQuery => $this->into($query->query),
            $query instanceof SetOperation, $query instanceof OrderedSetOperation, $query instanceof LeadingUnion => $this->into($query->left) || $this->into($query->right),
            default => false,
        };
    }
}
