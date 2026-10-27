<?php

declare(strict_types=1);

namespace MySqlMemory\Program\Write;

use LogicException;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Problem\Errors;
use MySqlMemory\Session\Session;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\SearchPath;
use SqlSemantics\Platform\MySql\Statement\Dml\Update;
use SqlSemantics\Statement\Reference\Table\MissingTable;

/**
 * Prepares a complete retained UPDATE before reporting its fixed input, target or clause constraint.
 *
 * @visibility MySqlMemory
 */
final class UpdateUse
{
    /**
     * Obtains the standalone request's first preparation condition.
     */
    public function error(Update|\SqlSemantics\Platform\MySql\Statement\Dml\Problem\Update\ConflictingUpdate $write, Session $session): SqlError
    {
        try {
            $this->raise($write, $session);
        } catch (SqlError $error) {
            return (new \MySqlMemory\Session\Problem\Precision())->legacy($error, $session->settings()->release());
        }
    }

    /**
     * Opens the original inputs in the current state without replacing the statement.
     *
     * @param list<array{int|float|string|null, \MySqlMemory\Typing\Domain}> $parameters Bound marker values
     * @throws SqlError When a dependency or retained input constraint is reached
     * @throws LogicException When the retained constraint loses its native error
     */
    public function raise(Update|\SqlSemantics\Platform\MySql\Statement\Dml\Problem\Update\ConflictingUpdate $write, Session $session, array $parameters = []): never
    {
        $this->check($write, $session, parameters: $parameters);
        throw new LogicException('A retained UPDATE keeps its input, target or clause contradiction.');
    }

    /**
     * Checks reached preparation phases while retaining deferred assignment values.
     *
     * @param list<array{int|float|string|null, \MySqlMemory\Typing\Domain}> $parameters Bound marker values
     * @throws SqlError When an input, destination or reached clause cannot bind
     */
    public function check(Update|\SqlSemantics\Platform\MySql\Statement\Dml\Problem\Update\ConflictingUpdate $write, Session $session, bool $prepared = false, array $parameters = []): void
    {
        $database = $session->variables->database;
        $context = $session->semantics()->context(\MySqlMemory\Session\Statement\Catalog::declarations($session), true, $database === '' ? null : new SearchPath($database), $session->resolution((new \MySqlMemory\Session\Statement\Binding())->parameters($session, $parameters)));
        $write = \SqlSemantics\Platform\MySql\Construction\Occurrences::fresh($write);
        $derivation = new Derivation($context);
        if ($write instanceof Update) {
            $write->deriveStatement($derivation);
        } else {
            $write->derive($derivation);
        }
        $facts = (new \SqlSemantics\Platform\MySql\Construction\StatementResults())->of($derivation);
        (new \MySqlMemory\Session\Access\RelationEntry())->counts($write, $facts, $session);
        (new \MySqlMemory\Session\Access\DefaultDatabase())->write($write, $facts, $session);
        foreach ($facts->diagnostics as $problem) {
            if (\MySqlMemory\Session\Problem\Stages::parsed($problem)) {
                throw (new Errors())->error($problem, $session);
            }
        }
        $this->names($write, $facts, $session);
        (new \MySqlMemory\Session\Problem\Reading())->legacyWrites($write, $facts, $session);
        foreach ($facts->diagnostics as $problem) {
            if ($problem instanceof MissingTable || $problem instanceof \SqlSemantics\Platform\MySql\Statement\Query\Problem\UnpartitionedTable || $problem instanceof \SqlSemantics\Platform\MySql\Statement\Query\Problem\UnknownPartition) {
                throw (new Errors())->error($problem, $session);
            }
        }
        $input = (new \MySqlMemory\Session\JsonTables())->first($write, $facts);
        if ($input !== null) {
            throw $input;
        }
        (new \MySqlMemory\Session\Problem\Sampling())->write($facts);
        if ($write instanceof Update) {
            if ((new UpdateOperands())->prepare($write, $facts, $session, $prepared, $parameters)) {
                return;
            }
        }
        foreach ($facts->diagnostics as $problem) {
            throw (new Errors())->error($problem, $session, statement: $write);
        }

    }
    /**
     * Orders implicit input databases and collisions by their semantic occurrences.
     * A derived input completes before its alias is registered. Bound CTE identities
     * activate their bodies; unused definitions do not contribute a collision.
     *
     * @throws SqlError When a reached input has no database or repeats a fixed name
     */
    public function names(Update|\SqlSemantics\Platform\MySql\Statement\Dml\Problem\Update\ConflictingUpdate $write, \SqlSemantics\Platform\MySql\Statement\StatementSemantics $facts, Session $session): void
    {
        $collisions = [];
        foreach ($facts->diagnostics as $problem) {
            if ($problem instanceof \SqlSemantics\Platform\MySql\Statement\Server\Problem\NonUniqueTable && $problem->relation !== null) {
                $collisions[spl_object_id($problem->relation)] = $problem;
            }
        }
        $inputs = [...$write->tables, ...$write->assignments, ...($write->where === null ? [] : [$write->where]), ...$write->orderBy];
        foreach ($inputs as $input) {
            foreach ((new \MySqlMemory\Evaluation\Compile\Walker())->resolved($input, $facts, true) as $node) {
                if ($session->variables->database === '' && ($node instanceof \SqlSemantics\Platform\MySql\Statement\Relation\TableReference || $node instanceof \SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable) && $node->hasBinding()) {
                    $table = $node->relationInformation()->table;
                    if ($table instanceof MissingTable && $table->name->schema === null) {
                        throw \MySqlMemory\Error\Family\QueryError::NoDatabase->error();
                    }
                }
                if (isset($collisions[spl_object_id($node)])) {
                    throw (new Errors())->error($collisions[spl_object_id($node)], $session);
                }
            }
        }
    }

}
