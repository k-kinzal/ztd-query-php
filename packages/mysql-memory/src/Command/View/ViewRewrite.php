<?php

declare(strict_types=1);

namespace MySqlMemory\Command\View;

use LogicException;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Evaluation\Compile\Walker;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Construction\Occurrences as Reconstruction;
use SqlSemantics\Platform\MySql\Statement\Dml\Assignment;
use SqlSemantics\Platform\MySql\Statement\Dml\Delete;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\ColumnList;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertInto;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertQuery;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertSet;
use SqlSemantics\Platform\MySql\Statement\Dml\Update;
use SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Logical;
use SqlSemantics\Platform\MySql\Statement\Expression\LogicalOperator;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Platform\MySql\Statement\Statement as MySqlStatement;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Statement;

/**
 * Translates a write through a view using bound occurrences and typed operands only.
 *
 * Column references are substituted by their resolved relation identity, so a name in
 * an inner scope that happens to be spelled the same way keeps its own meaning.
 *
 * @visibility MySqlMemory
 */
final class ViewRewrite
{
    /**
     * Reconstructs a statement against the base table and resolves the new structure.
     *
     * @throws LogicException When a retained predicate condition bypasses preparation
     */
    public function operation(MySqlStatement $operation, ViewWrites $writes, TableReference|WriteTarget $target, \MySqlMemory\Session\Session $session): MySqlStatement
    {
        $statement = $operation;
        $names = [];
        foreach ((new Walker())->find($statement, Query::class) as $query) {
            foreach ($query->queryInformation()->projection as $field) {
                if ($field instanceof Field && $field->expression !== null && $field->name !== null) {
                    $names[spl_object_id($field->expression)] = $field->name;
                }
            }
        }
        $copy = null;
        $copy = new Reconstruction(function (Node $node) use ($operation, $writes, $target, $names, &$copy): ?Node {
            Check::invariant($copy !== null, 'The reconstruction is initialized before it visits operands.');

            return $this->replacement($node, $operation, $writes, $target, $copy, $names);
        });
        $rewritten = $copy->node($statement);
        assert($rewritten instanceof Statement);
        if (($rewritten instanceof Update || $rewritten instanceof Delete) && $writes->where !== null) {
            $where = (new Reconstruction())->node($writes->where);
            assert($where instanceof Scalar);
            if ($rewritten->where !== null && !$rewritten->where instanceof Scalar) {
                throw new LogicException('A view write must resolve its predicate condition before rewriting.');
            }
            $where = $rewritten->where === null ? $where : new Logical(LogicalOperator::And, new Grouped($rewritten->where), new Grouped($where));
            $rewritten = (new Reconstruction())->node($rewritten, ['where' => $where]);
            assert($rewritten instanceof Statement);
        }

        return (new \MySqlMemory\Session\Statement\Binding())->operation($session, $rewritten, profile: $operation->profile());
    }

    /**
     * Replaces bound occurrences while preserving the resolved names of projected expressions.
     *
     * @param array<int, Name> $names
     */
    public function replacement(Node $node, MySqlStatement $operation, ViewWrites $writes, TableReference|WriteTarget $target, Reconstruction $copy, array $names): ?Node
    {
        $statement = $operation;
        $insert = $statement instanceof InsertRows || $statement instanceof InsertSet || $statement instanceof InsertQuery;
        if ($node === $target) {
            return $target instanceof TableReference
                ? new TableReference($writes->table, $writes->alias)
                : new WriteTarget($writes->table, $insert ? null : $writes->alias);
        }
        if ($node instanceof InsertInto) {
            return $this->insertion($node, $writes, $target, $statement instanceof InsertSet);
        }
        $assignments = $statement instanceof Update || $statement instanceof InsertSet ? $statement->assignments : [];
        $assignments = [...$assignments, ...($insert ? $statement->onDuplicate : [])];
        if ($node instanceof Assignment && in_array($node, $assignments, true)) {
            $value = $copy->node($node->value);
            assert($value instanceof Scalar);

            return new Assignment(new ColumnUse($writes->written($node->column->name->value)), $value, operator: $node->operator);
        }
        if ($node instanceof SelectExpression) {
            $value = $copy->node($node->expression);
            assert($value instanceof Scalar);
            /** A changed expression keeps its resolved output name, not its old spelling layout. */
            return (new \SqlSemantics\Validation\Equivalence())->difference($value, $node->expression) === null
                ? new SelectExpression($value, $node->alias, $node->layout, $node->mark, $node->spellingProfile)
                : new SelectExpression($value, $names[spl_object_id($node->expression)] ?? $node->alias);
        }
        if ($node instanceof ColumnUse && $node->hasBinding()) {
            $resolution = $node->scalarInformation()->resolution;
            if ($resolution instanceof ResolvedColumn && $resolution->relation === $target) {
                return $writes->column($node->name->value)[2] ?? null;
            }
        }

        return null;
    }

    /**
     * Maps the explicit or implicit insertion column list to base columns.
     */
    public function insertion(InsertInto $node, ViewWrites $writes, TableReference|WriteTarget $target, bool $set): InsertInto
    {
        $columns = $node->columns;
        if (!$set) {
            $names = $columns === null ? array_column($writes->columns, 0) : array_map(static fn ($name): string => $name instanceof ColumnUse ? $name->name->value : throw QueryError::BadField->error('*', 'field list'), $columns->columns);
            $columns = new ColumnList(array_map(static fn (string $name): ColumnUse => new ColumnUse($writes->written($name)), $names));
        }
        foreach ($writes->columns as $column) {
            if ($column[1] === null) {
                throw QueryError::NonInsertableTable->error($target->name->name->value, 'INSERT');
            }
        }

        return new InsertInto($node->replace, $node->priority, $node->ignore, new WriteTarget($writes->table), $columns, $node->hints);
    }
}
