<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile;

use ReflectionClass;
use SqlSemantics\Platform\MySql\Rules\Typing\Materialization;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\With\CommonTableExpression;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Statement\Fact\Facts;
use SqlSemantics\Statement\NamedRelation;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Reference\Table\CommonTable;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Reference\Table\TableResolution;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OutputSlot;

/**
 * Prints an expression as the server prints it in messages: operators parenthesized, keywords in lower case.
 *
 * With the facts of the statement, a column is printed as the column it reads: a column of a
 * table as its database, the correlation name of the table and its declared name; a column of
 * a derived table or common table expression that merges into the query as the expression that
 * defines it; a column of one that is materialized as its correlation name and column name.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/derived-table-optimization.html.
 *
 * @visibility MySqlMemory
 */
final class Printer
{
    /**
     * @param Facts|null $facts The facts of the statement, which resolve its columns; without them a column is printed as written
     * @param string $database The current database, which holds a table named without one
     */
    public function __construct(public readonly ?Facts $facts = null, public readonly string $database = '')
    {
    }

    /**
     * Prints an expression.
     */
    public function expression(Scalar $node): string
    {
        return match (true) {
            $node instanceof Grouped => $this->expression($node->operand),
            $node instanceof Arithmetic => '(' . $this->expression($node->left) . ' ' . strtolower($node->operator->value) . ' ' . $this->expression($node->right) . ')',
            $node instanceof Unary => $node->operator->value . '(' . $this->expression($node->operand) . ')',
            $node instanceof \SqlSemantics\Platform\MySql\Statement\Call\KeywordCall => strtolower($node->function->value) . '(' . implode(',', array_map(fn ($argument): string => $this->expression($argument), $node->arguments)) . ')',
            $node instanceof \SqlSemantics\Platform\MySql\Statement\Call\FunctionCall => strtolower($node->name->value) . '(' . implode(',', array_map(fn ($argument): string => $this->expression($argument->expression), $node->arguments)) . ')',
            $node instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast => 'cast(' . $this->expression($node->operand) . ' as ' . strtolower($node->target->kind->value) . ($node->target->length === null ? '' : '(' . $node->target->length . ($node->target->scale === null ? '' : ',' . $node->target->scale) . ')') . ')',
            $node instanceof NumberLiteral => $node->text,
            $node instanceof StringLiteral => "'" . str_replace("'", "\\'", $node->value()) . "'",
            $node instanceof NullLiteral => 'NULL',
            $node instanceof ColumnUse => $this->column($node),
            default => strtolower((new ReflectionClass($node))->getShortName()),
        };
    }

    /**
     * Prints a column name: the column it reads when the facts resolve it, else as written.
     */
    public function column(ColumnUse $node): string
    {
        $written = ($node->qualifier === null ? '' : '`' . $node->qualifier->name->value . '`.') . '`' . $node->name->value . '`';
        if ($this->facts === null || !$this->facts->covers($node)) {
            return $written;
        }
        $resolution = $this->facts->scalar($node)->resolution;
        if ($resolution instanceof AliasTarget) {
            return $this->field($resolution->field) ?? $written;
        }

        return $resolution instanceof ResolvedColumn ? $this->resolved($resolution) : $written;
    }

    /**
     * Prints an output field of a query: its expression, or the column it reads; null when it has neither.
     */
    public function field(Field $field): ?string
    {
        if ($field->expression !== null) {
            return $this->expression($field->expression);
        }

        return $field->resolution instanceof ResolvedColumn ? $this->resolved($field->resolution) : null;
    }

    /**
     * Prints a resolved column: of a table, of a merged derived table or common table expression, or of a materialized one.
     */
    public function resolved(ResolvedColumn $resolution): string
    {
        $relation = $resolution->relation;
        $table = $this->facts !== null && $this->facts->covers($relation) ? $this->facts->relation($relation)->table : null;
        if ($table instanceof DeclaredTable && $relation instanceof NamedRelation) {
            return $this->tableColumn($resolution, $table, $relation);
        }

        return $this->merged($resolution, $table) ?? $this->materialized($resolution);
    }

    /**
     * Prints a column of a table: its database, unless the table has none, the correlation name of the table and the declared name of the column.
     */
    public function tableColumn(ResolvedColumn $resolution, DeclaredTable $table, NamedRelation $relation): string
    {
        $declared = $resolution->slot->declaration();
        $schema = $table->table->name->schema->value ?? $relation->name()->schema->value ?? $this->database;

        return ($schema === '' ? '' : '`' . $schema . '`.') . '`' . ($relation->alias() ?? $relation->name()->name)->value . '`.`' . ($declared->name->value ?? $resolution->slot->name->value ?? '') . '`';
    }

    /**
     * Prints a column of a derived table or common table expression that merges into the query as the field that defines it; null when it does not merge or the field prints as nothing.
     */
    public function merged(ResolvedColumn $resolution, ?TableResolution $table): ?string
    {
        $relation = $resolution->relation;
        $query = match (true) {
            $relation instanceof DerivedTable => $relation->query,
            $table instanceof CommonTable && $table->definition instanceof CommonTableExpression => $table->definition->query,
            default => null,
        };
        if ($query === null || $this->facts === null || !(new Materialization())->mergeable($query)) {
            return null;
        }
        $field = $this->facts->query($query)->projection[$this->position($resolution)] ?? null;

        return $field instanceof Field ? $this->field($field) : null;
    }

    /**
     * Prints a column of a materialized relation: the correlation name of the relation, when it has one, and the column name.
     */
    public function materialized(ResolvedColumn $resolution): string
    {
        $relation = $resolution->relation;
        $alias = match (true) {
            $relation instanceof DerivedTable => $relation->alias,
            $relation instanceof NamedRelation => $relation->alias() ?? $relation->name()->name,
            default => null,
        };

        return ($alias === null ? '' : '`' . $alias->value . '`.') . '`' . ($resolution->slot->name->value ?? '') . '`';
    }

    /**
     * Answers the position of a resolved column among the columns of its relation occurrence, or -1 when it is not one of them.
     */
    public function position(ResolvedColumn $resolution): int
    {
        if ($this->facts === null) {
            return -1;
        }
        $slots = $this->facts->relation($resolution->relation)->shape->slots;
        for ($slot = $resolution->slot; $slot instanceof OutputSlot; $slot = $slot->origin) {
            $position = array_search($slot, $slots, true);
            if (is_int($position)) {
                return $position;
            }
        }

        return -1;
    }
}
