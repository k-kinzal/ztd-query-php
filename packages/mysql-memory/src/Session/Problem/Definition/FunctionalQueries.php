<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Problem\Definition;

use MySqlMemory\Command\Definition\Expression\ExpressionRole;
use MySqlMemory\Command\Definition\Expression\ExpressionRules;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Session\Problem\Errors;
use MySqlMemory\Session\Problems;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns;
use SqlSemantics\Platform\MySql\Statement\Statement as MySqlStatement;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ExpressionPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;
use SqlSemantics\Statement\Query;

/**
 * Opens functional-index dependencies in their statement ownership order.
 *
 * References are already resolved by SQL Semantics. CREATE TABLE opens its
 * subquery dependencies before testing target existence; ALTER and CREATE INDEX
 * open their existing target first. Missing key columns and forbidden-subquery
 * diagnostics follow these relation dependencies.
 * Explicit row-width conflicts precede table lookup. Verified through SQL
 * on MySQL 8.0.44, 8.4.7 and 9.1.0 without consulting implementation code.
 *
 * @visibility MySqlMemory
 */
final class FunctionalQueries
{
    /**
     * Refuses explicit row keys before resolving their element names and calls.
     * Target lookup and conditional creation have already completed at this point.
     */
    public function rows(\SqlSemantics\Statement\Node $statement): void
    {
        if ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Table\CreateIndex) {
            $indexes = [new IndexDefinition($statement->kind, $statement->parts, new \SqlSemantics\Platform\MySql\Statement\Name\ColumnName($statement->name), $statement->algorithm, $statement->options)];
        } else {
            $indexes = (new Walker())->find($statement, IndexDefinition::class);
        }
        foreach ($indexes as $index) {
            foreach ($index->parts as $part) {
                if ($part instanceof \SqlSemantics\Platform\MySql\Statement\Table\Key\Deferred\ExpressionPart && $part->expression instanceof \SqlSemantics\Platform\MySql\Statement\Expression\RowOperand) {
                    (new \MySqlMemory\Command\Definition\Constraint\FunctionalKeys())->check($part, $index);
                }
            }
        }
    }

    /**
     * Prepares referenced tables before target existence and index validation.
     *
     * @throws \MySqlMemory\Error\SqlError When an explicit width or a referenced table is invalid
     */
    public function prepare(MySqlStatement|\MySqlMemory\Session\Resolution\IndexPreparation $operation, Session $session, Problems $problems): void
    {
        $create = $operation instanceof MySqlStatement ? $operation : $operation->statement;
        $index = $create instanceof \SqlSemantics\Platform\MySql\Statement\Table\CreateIndex;
        $alter = $create instanceof \SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
        if ((!$create instanceof CreateTable && !$alter && !$index) || $session->settings()->legacy()) {
            return;
        }
        $indexes = $index ? $create->parts : (new Walker())->find($create, IndexDefinition::class);
        $walker = new Walker();
        if (array_filter($indexes, static fn (\SqlSemantics\Statement\Node $index): bool => (new Walker())->find($index, Query::class) !== []) === []) {
            return;
        }
        foreach (($operation instanceof MySqlStatement ? $operation->statementInformation() : $operation->facts)->diagnostics as $diagnostic) {
            if ($diagnostic instanceof OperandColumns && $diagnostic->expression !== null && $walker->find($diagnostic->expression, Query::class) === []) {
                throw (new Errors())->error($diagnostic, $session, 'field list', $create);
            }
        }
        foreach (($operation instanceof MySqlStatement ? $operation->statementInformation() : $operation->facts)->diagnostics as $diagnostic) {
            if (($alter || $index) && $diagnostic instanceof \SqlSemantics\Statement\Reference\Table\MissingTable && $diagnostic->name === $create->table) {
                throw (new Errors())->error($diagnostic, $session);
            }
        }
        $problems->opened($operation, $session, ($operation instanceof MySqlStatement ? $operation->statementInformation() : $operation->facts)->diagnostics);
    }

    /**
     * Prepares added functional operands before constructing the changed table layout.
     *
     * @param list<IndexDefinition> $indexes The requested indexes in action order
     * @throws \MySqlMemory\Error\SqlError When an indexed operand cannot be prepared
     */
    public function added(array $indexes, \SqlSemantics\Platform\MySql\Statement\StatementSemantics $facts, Session $session): void
    {
        foreach ($indexes as $index) {
            foreach ($index->parts as $part) {
                if (!$part instanceof ExpressionPart && !$part instanceof \SqlSemantics\Platform\MySql\Statement\Table\Key\ArrayPart && !$part instanceof \SqlSemantics\Platform\MySql\Statement\Table\Key\Deferred\ExpressionPart) {
                    continue;
                }
                (new \MySqlMemory\Command\Definition\Constraint\FunctionalKeys())->check($part, $index);
                $owner = $index->name?->column->value ?? $index->constraint?->name?->column->value ?? 'functional_index';
                (new ExpressionRules())->forbidden($part->expression, ExpressionRole::Functional, $owner);
                (new ExpressionRules())->grouped($part->expression);
                $nodes = (new Walker())->find($part->expression, \SqlSemantics\Statement\Node::class);
                foreach ($nodes as $node) {
                    if ($node instanceof \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse) {
                        $resolution = $node->scalarInformation()->resolution;
                        if ($resolution instanceof \SqlSemantics\Statement\Reference\Column\MissingColumn) {
                            throw (new Errors())->error($resolution, $session, 'functional index');
                        }
                    }
                }
                if ($part instanceof \SqlSemantics\Platform\MySql\Statement\Table\Key\Deferred\ExpressionPart) {
                    \SqlSemantics\Diagnostic\Check::invariant(!$part->expression instanceof \SqlSemantics\Statement\Scalar, 'A bare functional column is refused before row preparation.');
                    if ($part->expression instanceof \SqlSemantics\Platform\MySql\Statement\Expression\RowOperand) {
                        throw \MySqlMemory\Error\Family\ConstraintError::FunctionalRow->error($owner);
                    }
                    throw (new \MySqlMemory\Program\Expression\RowUse())->error($part->expression);
                }
                foreach ($facts->diagnostics as $diagnostic) {
                    if ($diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Problem\TimeZoneConversion && in_array($diagnostic->expression, $nodes, true)) {
                        throw (new Errors())->error($diagnostic, $session);
                    }
                }
            }
        }
    }

    /**
     * Selects calls whose routine lookup is independent of functional-key preparation.
     * A key's forbidden-function condition precedes lookup of a stored function.
     *
     * @return list<\SqlSemantics\Platform\MySql\Statement\Call\FunctionCall>
     */
    public function ordinaryCalls(\SqlSemantics\Statement\Node $statement): array
    {
        $walker = new Walker();
        $keyCalls = [];
        foreach ($walker->find($statement, \SqlSemantics\Platform\MySql\Statement\Table\Key\KeyPart::class) as $part) {
            array_push($keyCalls, ...$walker->find($part, \SqlSemantics\Platform\MySql\Statement\Call\FunctionCall::class));
        }
        return array_values(array_filter($walker->find($statement, \SqlSemantics\Platform\MySql\Statement\Call\FunctionCall::class), static fn ($call): bool => !in_array($call, $keyCalls, true)));
    }

    /**
     * Leaves indexed conversion conditions to the index's preparation order.
     */
    public function deferred(\SqlSemantics\Statement\Fact\Diagnostic $diagnostic, \SqlSemantics\Statement\Node $statement): bool
    {
        if ($diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\InvalidIndexName || $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\SpatialKeyArity || $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\FunctionalIndexKind) {
            return true;
        }
        if (!$diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Problem\TimeZoneConversion) {
            return false;
        }
        $owners = ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Table\CreateIndex) ? [$statement] : (new Walker())->find($statement, IndexDefinition::class);
        foreach ($owners as $index) {
            if (in_array($diagnostic->expression, (new Walker())->find($index, \SqlSemantics\Statement\Node::class), true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Refuses index subqueries after target existence, before ordinary key columns.
     *
     * @throws \MySqlMemory\Error\SqlError When an index expression contains a forbidden construct
     */
    public function forbidden(CreateTable|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Definition\DeferredCreateTable $create): void
    {
        $indexes = array_values(array_filter($create->elements, static fn ($element): bool => $element instanceof IndexDefinition));
        if (array_filter($indexes, static fn (IndexDefinition $index): bool => (new Walker())->find($index, Query::class) !== []) === []) {
            return;
        }
        foreach ($indexes as $index) {
            foreach ($index->parts as $part) {
                if ($part instanceof ExpressionPart || $part instanceof \SqlSemantics\Platform\MySql\Statement\Table\Key\Deferred\ExpressionPart) {
                    (new \MySqlMemory\Command\Definition\Constraint\FunctionalKeys())->check($part, $index);
                    (new ExpressionRules())->forbidden($part->expression, ExpressionRole::Functional, $index->name?->column->value ?? $index->constraint?->name?->column->value ?? 'functional_index');
                }
            }
        }
    }
}
