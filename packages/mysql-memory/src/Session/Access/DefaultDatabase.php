<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Access;

use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Dml\MultipleDelete;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateEvent;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowFunctionCode;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowProcedureCode;
use SqlSemantics\Statement\Statement;

/**
 * Resolves an implicit object database before interpreting the object's later clauses.
 *
 * DELETE targets require a default database even when their FROM tables are
 * qualified. Event names precede body diagnostics, and routine CODE names
 * precede the release-build restriction. Observed on 5.6, 8.0 and 8.4.
 * Only the statement's own names are inspected; stored bodies are deferred.
 *
 * @visibility MySqlMemory
 */
final class DefaultDatabase
{
    /**
     * Checks the names whose implicit database is needed before their later clauses.
     * Modern DELETE collects grammar notices before resolving its target names;
     * MySQL 5.6 DELETE and stored-definition names resolve while reading the header.
     *
     * @param bool $afterParsing Whether grammar notices have been recorded
     * @throws SqlError When an unqualified name requires a current database
     */
    public function check(Statement $statement, Session $session, bool $afterParsing = false): void
    {
        $database = $session->variables->database;
        if ($statement instanceof Explain) {
            $database = $statement->database->value ?? $database;
            $statement = $statement->statement;
        }
        if ($database !== '') {
            return;
        }
        if ($statement instanceof MultipleDelete && $session->settings()->release() !== \SqlSemantics\Contract\GrammarRelease::MySql5651 && !$afterParsing) {
            return;
        }
        $names = match (true) {
            $statement instanceof MultipleDelete => $statement->targets,
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadTable && $session->settings()->legacy() => [$statement->table->name],
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction,
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure,
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateTrigger,
            $statement instanceof CreateEvent,
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\AlterEvent,
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\AlterRoutine,
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\DropProgram && $statement->kind !== \SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind::Function,
            $statement instanceof ShowFunctionCode,
            $statement instanceof ShowProcedureCode => [$statement->name],
            default => [],
        };
        foreach ($names as $name) {
            if ($name->schema === null) {
                throw QueryError::NoDatabase->error();
            }
        }
    }

    /**
     * Checks reached base-table references before statement-level hints are applied.
     * A CTE or an unused CTE body does not need a default database merely because
     * its spelling is unqualified; the resolved diagnostics distinguish them.
     *
     * @throws SqlError When a reached base-table reference requires a current database
     */
    public function referenced(\SqlSemantics\Statement\Operation $operation, Session $session): void
    {
        if ($session->variables->database !== '' || \MySqlMemory\Command\Dispatcher::program($operation->statement)) {
            return;
        }
        $this->calls($operation->statement, $session);
        $names = [];
        foreach ((new \MySqlMemory\Session\Problems())->reached($operation->statement) as $node) {
            if ($node instanceof \SqlSemantics\Platform\MySql\Statement\Relation\TableReference || $node instanceof \SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable || $node instanceof \SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget) {
                $names[spl_object_id($node->name())] = true;
            }
        }
        foreach ($operation->facts->diagnostics as $diagnostic) {
            if ($diagnostic instanceof \SqlSemantics\Statement\Reference\Table\MissingTable && $diagnostic->name->schema === null && isset($names[spl_object_id($diagnostic->name)])) {
                throw QueryError::NoDatabase->error();
            }
        }
    }

    /**
     * Resolves implicit stored-function databases before tables and scalar operands.
     * Unused CTE definitions do not register calls. A selected database leaves
     * missing routine lookup at the call's ordinary expression position.
     *
     * @throws SqlError When a reached stored function has no database
     */
    public function calls(\SqlSemantics\Statement\Node $statement, Session $session): void
    {
        if ($session->variables->database !== '' || ($statement instanceof Statement && \MySqlMemory\Command\Dispatcher::program($statement))) {
            return;
        }
        foreach ((new \SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadDependencies())->nodes($statement) as $node) {
            if (($node instanceof \SqlSemantics\Platform\MySql\Statement\Call\FunctionCall || $node instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredCall) && $node->schema === null && !$node->native($session->settings()->release())) {
                throw (new \MySqlMemory\Session\Problem\Errors())->routine($node, $session);
            }
        }
    }
}
