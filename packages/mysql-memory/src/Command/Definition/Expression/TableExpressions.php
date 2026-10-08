<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition\Expression;

use MySqlMemory\Dictionary\ColumnDefinition;
use MySqlMemory\Dictionary\Fill;
use MySqlMemory\Dictionary\Key;
use MySqlMemory\Dictionary\KeyKind;
use MySqlMemory\Dictionary\TableDefinition;
use MySqlMemory\Error\Family\ConstraintError;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Planner;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition as ColumnElement;
use SqlSemantics\Platform\MySql\Statement\Table\Column\DefaultExpression;
use SqlSemantics\Platform\MySql\Statement\Table\Column\GeneratedColumn;
use SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\GeneratedStorage;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Scalar;

/**
 * Compiles the generated columns and the expression defaults of a table a CREATE TABLE statement declares, over a row of the table.
 *
 * A generated column may read the columns of its table, but a generated column only when it
 * comes before it, and no AUTO_INCREMENT column; an expression default may read any column but
 * itself, a later generated column, a later column with an expression default, or an
 * AUTO_INCREMENT column. A virtual generated column cannot be part of the primary key. The
 * expressions are kept as SHOW CREATE TABLE writes them (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table-generated-columns.html,
 * https://dev.mysql.com/doc/refman/8.4/en/data-type-defaults.html.
 *
 * @visibility MySqlMemory
 */
final class TableExpressions
{
    /**
     * @param Planner $planner The planner of the statement, which compiles the expressions
     * @param CreateTable $create The statement that declares the table
     */
    public function __construct(public readonly Planner $planner, public readonly CreateTable $create)
    {
    }

    /**
     * Answers the column definitions of the statement in order.
     *
     * @return list<ColumnElement>
     */
    public function elements(): array
    {
        return array_values(array_filter($this->create->elements, static fn ($element): bool => $element instanceof ColumnElement));
    }

    /**
     * Answers the expression default of a column definition, or null when it has none.
     */
    public function defaulted(ColumnElement $element): ?Scalar
    {
        foreach ($element->specification->columnAttributes() as $attribute) {
            if ($attribute instanceof DefaultExpression) {
                return $attribute->expression;
            }
        }

        return null;
    }

    /**
     * Refuses the first subquery, variable or refused function of the generated columns and expression defaults, in column order.
     *
     * @throws SqlError When an expression holds one
     */
    public function forbidden(): void
    {
        $rules = new ExpressionRules();
        foreach ($this->elements() as $element) {
            $specification = $element->specification;
            if ($specification instanceof GeneratedColumn) {
                $rules->forbidden($specification->expression, ExpressionRole::Generated, $element->name->column->value);
            }
            $default = $this->defaulted($element);
            if ($default !== null) {
                $rules->forbidden($default, ExpressionRole::Default, $element->name->column->value);
            }
        }
    }

    /**
     * Refuses a virtual generated column in the primary key.
     *
     * @param list<ColumnDefinition> $columns
     * @param list<Key> $keys
     *
     * @throws SqlError When the primary key holds one
     */
    public function keyed(array $columns, array $keys): void
    {
        $elements = $this->elements();
        foreach ($keys as $key) {
            foreach ($key->kind === KeyKind::Primary ? $key->columns : [] as $position) {
                $specification = $elements[$position]->specification;
                if ($specification instanceof GeneratedColumn && $specification->storage !== GeneratedStorage::Stored && isset($columns[$position])) {
                    throw ConstraintError::GeneratedUnsupported->error('Defining a virtual generated column as primary key');
                }
            }
        }
    }

    /**
     * Answers the scope a row of the table is read in: the table at the start of the row.
     */
    public function scope(TableDefinition $definition): Scope
    {
        $scope = new Scope();
        $scope->place($this->create, array_map(static fn (ColumnDefinition $column) => $column->domain, $definition->columns), array_map(static fn (ColumnDefinition $column): string => $column->name, $definition->columns), $definition);

        return $scope;
    }

    /**
     * Compiles the generated columns, then the expression defaults, and answers the definition with them.
     *
     * @throws SqlError When an expression reads what it may not
     */
    public function computed(TableDefinition $definition, string $charset): TableDefinition
    {
        $scope = $this->scope($definition);
        $names = [];
        foreach ($definition->columns as $column) {
            $names[mb_strtolower($column->name)] = $column->name;
        }
        $text = new ItemText($names, $charset, $this->planner->settings->release());
        $columns = $definition->columns;
        foreach ($this->elements() as $position => $element) {
            $specification = $element->specification;
            if ($specification instanceof GeneratedColumn) {
                (new ExpressionRules())->grouped($specification->expression);
                $this->generatedReferences($specification->expression, $position, $columns);
                array_splice($columns, $position, 1, [$columns[$position]->withGeneration($this->compiled($specification->expression, $scope), $specification->storage === GeneratedStorage::Stored, $text->text($specification->expression))]);
            }
        }
        foreach ($this->elements() as $position => $element) {
            $default = $this->defaulted($element);
            if ($default !== null && !$columns[$position]->default->now) {
                (new ExpressionRules())->grouped($default);
                $this->defaultReferences($default, $position, $definition->columns);
                $evaluable = $this->compiled($default, $scope);
                $written = $text->text($default);
                array_splice($columns, $position, 1, [$columns[$position]->withDefault(new Fill(true, null, $evaluable, false, $written))]);
            }
        }

        return $definition->withColumns($columns);
    }

    /**
     * Compiles an expression over a row of the table.
     *
     * @throws SqlError When the expression cannot be compiled
     */
    public function compiled(Scalar $expression, Scope $scope): Evaluable
    {
        return $this->planner->compiler->compile($expression, $scope);
    }

    /**
     * Answers the position in the table of each column an expression reads, in written order, or the name of the first that is not a column of the table.
     *
     * @param list<ColumnDefinition> $columns
     * @return list<int|string>
     */
    public function references(Scalar $expression, array $columns): array
    {
        $facts = $this->planner->compiler->facts;
        $positions = [];
        foreach ((new Walker())->find($expression, ColumnUse::class) as $use) {
            $resolution = $facts->covers($use) ? $facts->scalar($use)->resolution : null;
            $declaration = $resolution instanceof ResolvedColumn ? $resolution->slot->declaration() : null;
            $found = null;
            foreach ($columns as $position => $column) {
                if ($declaration !== null && $column->declaration === $declaration) {
                    $found = $position;
                }
            }
            $positions[] = $found ?? $use->name->value;
        }

        return $positions;
    }

    /**
     * Refuses a generated column that reads an unknown column, itself, a later generated column or an AUTO_INCREMENT column.
     *
     * @param list<ColumnDefinition> $columns
     *
     * @throws SqlError When it does
     */
    public function generatedReferences(Scalar $expression, int $position, array $columns): void
    {
        $elements = $this->elements();
        foreach ($this->references($expression, $columns) as $reference) {
            if (is_string($reference)) {
                throw QueryError::BadField->error($reference, 'generated column function');
            }
            if ($reference === $position || ($reference > $position && $elements[$reference]->specification instanceof GeneratedColumn)) {
                throw ConstraintError::GeneratedNotPrior->error();
            }
            if ($columns[$reference]->autoIncrement) {
                throw ConstraintError::GeneratedAutoIncrement->error($columns[$position]->name);
            }
        }
    }

    /**
     * Refuses an expression default that reads an unknown column, itself, a later generated column or column with an expression default, or an AUTO_INCREMENT column.
     *
     * @param list<ColumnDefinition> $columns
     *
     * @throws SqlError When it does
     */
    public function defaultReferences(Scalar $expression, int $position, array $columns): void
    {
        $elements = $this->elements();
        foreach ($this->references($expression, $columns) as $reference) {
            if (is_string($reference)) {
                throw QueryError::BadField->error($reference, 'default value expression');
            }
            $computed = $elements[$reference]->specification instanceof GeneratedColumn || $this->defaulted($elements[$reference]) !== null;
            if ($reference === $position || ($reference > $position && $computed)) {
                throw ConstraintError::DefaultNotPrior->error($columns[$position]->name);
            }
            if ($columns[$reference]->autoIncrement) {
                throw ConstraintError::DefaultAutoIncrement->error($columns[$position]->name);
            }
        }
    }
}
