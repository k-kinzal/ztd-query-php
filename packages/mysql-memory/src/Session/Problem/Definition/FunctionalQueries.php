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
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ExpressionPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;
use SqlSemantics\Statement\Operation;
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
     * Prepares referenced tables before target existence and index validation.
     *
     * @throws \MySqlMemory\Error\SqlError When an explicit width or a referenced table is invalid
     */
    public function prepare(Operation $operation, Session $session, Problems $problems): void
    {
        $create = $operation->statement;
        if ((!$create instanceof CreateTable && !$create instanceof \SqlSemantics\Platform\MySql\Statement\Alter\AlterTable && !$create instanceof \SqlSemantics\Platform\MySql\Statement\Table\CreateIndex) || $session->settings()->legacy()) {
            return;
        }
        $indexes = $create instanceof \SqlSemantics\Platform\MySql\Statement\Table\CreateIndex ? $create->parts : (new Walker())->find($create, IndexDefinition::class);
        $walker = new Walker();
        if (array_filter($indexes, static fn (\SqlSemantics\Statement\Node $index): bool => (new Walker())->find($index, Query::class) !== []) === []) {
            return;
        }
        foreach ($operation->facts->diagnostics as $diagnostic) {
            if ($diagnostic instanceof OperandColumns && $diagnostic->expression !== null && $walker->find($diagnostic->expression, Query::class) === []) {
                throw (new Errors())->error($diagnostic, $session, 'field list', $create);
            }
        }
        foreach ($operation->facts->diagnostics as $diagnostic) {
            if (!$create instanceof CreateTable && $diagnostic instanceof \SqlSemantics\Statement\Reference\Table\MissingTable && $diagnostic->name === $create->table) {
                throw (new Errors())->error($diagnostic, $session);
            }
        }
        $problems->opened($operation, $session, $operation->facts->diagnostics);
    }

    /**
     * Leaves indexed conversion conditions to the index's preparation order.
     */
    public function deferred(\SqlSemantics\Statement\Fact\Diagnostic $diagnostic, \SqlSemantics\Statement\Node $statement): bool
    {
        if (!$diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Problem\TimeZoneConversion) {
            return false;
        }
        $owners = $statement instanceof \SqlSemantics\Platform\MySql\Statement\Table\CreateIndex ? [$statement] : (new Walker())->find($statement, IndexDefinition::class);
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
    public function forbidden(CreateTable $create): void
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
