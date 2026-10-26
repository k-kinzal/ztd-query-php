<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Write;

use LogicException;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Result\Completion;
use MySqlMemory\Session\Session;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertedRow;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertInto;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertStatement;
use SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain as ResolvedDomain;
use SqlSemantics\Statement\Fact\Facts;
use SqlSemantics\Statement\Type\Known;

/**
 * Executes semantic VALUES directly using the existing storage and conversion machinery.
 *
 * Types and destinations come from the statement. Database state supplies stored
 * columns, defaults, constraints and transactions; neither SQL nor an AST is read.
 *
 * @visibility MySqlMemory
 */
final class InsertValuesCommand implements \MySqlMemory\Command\Command
{
    /**
     * INSERT starts with an empty diagnostics area.
     */
    public function clearsDiagnostics(): bool
    {
        return true;
    }

    /**
     * Inserts character values using their expression types before column conversion.
     *
     * @throws \MySqlMemory\Error\SqlError When database state or a value rejects the write
     * @throws LogicException When an expression has no implemented execution or resolved type
     */
    public function execute(\SqlSemantics\Statement\AnalyzedStatement $statement, Session $session, Context $context, Connection $connection): Completion
    {
        assert($statement instanceof InsertStatement);
        [$table, $positions] = $this->prepare($statement, $session);
        $target = $statement->target;

        /**
         * The row writer's planner receives semantic values; it performs no analysis here.
         */
        $into = new InsertInto(false, null, false, new WriteTarget($target->resolvedName ?? $target->name));
        $rows = [];
        $values = [];
        foreach ($statement->values->rows as $row) {
            $literals = [];
            $computed = [];
            foreach ($row as $expression) {
                if (!$expression instanceof StringLiteral) {
                    throw new LogicException('VALUES execution has not implemented ' . $expression::class);
                }
                $type = $expression->dataType();
                if (!$type instanceof Known || !$type->descriptor instanceof ResolvedDomain) {
                    throw new LogicException('A string literal requires its resolved expression type before execution.');
                }
                $literals[] = $expression;
                $computed[] = [$expression->value(), Domain::of($type->descriptor, false)];
            }
            $rows[] = new InsertedRow($literals);
            $values[] = $computed;
        }
        $prepared = new InsertRows($into, $rows);
        $facts = new Facts([], [], [], [], null, []);
        (new \MySqlMemory\System\Guard())->check($prepared, $session, $facts);
        $planner = new Planner($prepared, $facts, $session->settings(), $connection, $session->instance->dictionary);
        $context->strict = $context->modes->strict();
        $session->transaction->touch($table);
        $writer = new Rows($table, $context, $planner, $into, [], $session);
        foreach ($values as $number => $row) {
            $writer->write($row === [] ? [] : $positions, $row, $number + 1, count($values) === 1);
        }
        return new Completion($writer->affected, $writer->identity->finish($session->variables), $context->diagnostics->raised(), count($values) > 1 ? sprintf('Records: %d  Duplicates: %d  Warnings: %d', count($values), $writer->duplicates, $context->diagnostics->raised()) : '');
    }
    /**
     * Resolves destination storage without evaluating or converting the row values.
     *
     * @return array{\MySqlMemory\Dictionary\StoredTable, list<int>}
     * @throws \MySqlMemory\Error\SqlError When the destination is absent or its columns do not match
     */
    public function prepare(InsertStatement $statement, Session $session): array
    {
        $target = $statement->target;
        $name = $target->resolvedName ?? $target->name;
        $schema = $name->schema->value ?? $session->variables->database;
        if ($schema === '') {
            throw QueryError::NoDatabase->error();
        }
        $table = $session->instance->dictionary->table($schema, $name->name->value);
        if ($table === null) {
            throw QueryError::NoSuchTable->error($schema, $name->name->value);
        }
        $positions = [];
        if ($target->columns === null || $target->columns === []) {
            foreach ($table->definition->columns as $position => $column) {
                if (!$column->invisible) {
                    $positions[] = $position;
                }
            }
        } else {
            foreach ($target->columns as $destination) {
                $found = null;
                foreach ($table->definition->columns as $position => $column) {
                    if (($destination->declaration !== null && $column->declaration === $destination->declaration) || strcasecmp($column->name, $destination->name->value) === 0) {
                        $found = $position;
                        break;
                    }
                }
                if ($found === null) {
                    throw QueryError::BadField->error($destination->name->value, 'field list');
                }
                if (in_array($found, $positions, true)) {
                    throw QueryError::FieldSpecifiedTwice->error($destination->name->value);
                }
                $positions[] = $found;
            }
        }

        $width = count($statement->values->rows[0]);
        if ($width !== 0 && $width !== count($positions)) {
            throw QueryError::WrongValueCountOnRow->error(1);
        }
        return [$table, $positions];
    }
}
