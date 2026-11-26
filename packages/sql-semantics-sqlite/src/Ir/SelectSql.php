<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir;

use SqlSemantics\Platform\Sqlite\Ir\Expression\CaseExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CastExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CollatedExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\Concatenation;
use SqlSemantics\Platform\Sqlite\Ir\Expression\Nulls\Coalesce;
use SqlSemantics\Platform\Sqlite\Ir\Expression\UnaryExpression;
use SqlSemantics\Platform\Sqlite\Ir\From\Join;
use SqlSemantics\Platform\Sqlite\Ir\Literal\BlobLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\HexIntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\RealLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Ordering\Limit;
use SqlSemantics\Platform\Sqlite\Ir\Ordering\Order;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\BetweenTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Comparison;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\FoldedMembership;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Logical;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\MembershipTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Pattern\PatternTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\TruthTest;
use SqlSemantics\Platform\Sqlite\Ir\Rows\LocalInputs;
use SqlSemantics\Platform\Sqlite\Ir\Rows\LocalSource;
use SqlSemantics\Platform\Sqlite\Ir\Rows\RecursiveDefinition;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Predicate\DefinitionSource;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;

/**
 * Emits a complete SQLite SELECT with canonical occurrence aliases.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class SelectSql
{
    private const SOURCE_ALIAS = '"__ztd_source"';

    private const CTE_NAME = '"__ztd_cte"';

    /**
     * Retains empty membership syntax when emitting a native index definition.
     */
    public function __construct(private readonly bool $preserveEmptyMembership = false)
    {
    }

    /**
     * Renders the SELECT, the join chain, the filter and the ordering in clause order.
     */
    public function render(Select $select, bool $requireWhere = false): string
    {
        $source = $select->source;
        $local = (new LocalInputs())->occurrences($source, $select->joins)[0] ?? null;
        $sql = $local === null ? '' : $this->localPrefix($local);
        $items = [];
        foreach ($select->columns as $column) {
            $items[] = $this->expression($column->expression, $source, $select->joins) . ' AS ' . $column->name->quoted();
        }
        $sql .= 'SELECT ' . ($select->distinct ? 'DISTINCT ' : '') . implode(', ', $items);
        if ($source !== null) {
            $sql .= ' FROM ' . $this->source($source, 0);
        }
        foreach ($select->joins as $index => $join) {
            $sql .= ' ' . $join->kind->value . ' JOIN ' . $this->source($join->source, $index + 1);
            if ($join->on !== null) {
                $sql .= ' ON ' . $this->expression($join->on, $source, $select->joins);
            }
        }
        if ($select->where !== null) {
            $sql .= ' WHERE ' . $this->expression($select->where, $source, $select->joins);
        } elseif ($requireWhere) {
            $sql .= ' WHERE 1';
        }
        return $sql . $this->ordering($select->orderBy) . ($select->limit === null ? '' : ' ' . $this->limit($select->limit));
    }

    /**
     * Emits the closed definition and its generated local name.
     */
    public function localPrefix(LocalSource $source): string
    {
        $names = [];
        foreach ($source->columns as $name) {
            $names[] = $name->quoted();
        }
        $with = 'WITH ';
        if ($source->query instanceof RecursiveDefinition) {
            $definition = $source->query;
            $with = 'WITH RECURSIVE ';
            $operands = [];
            foreach ($definition->step as $column) {
                $operands[] = $this->expression($column->expression, $source);
            }
            $query = $definition->anchor->toString() . ($definition->unionAll ? ' UNION ALL SELECT ' : ' UNION SELECT ')
                . implode(', ', $operands) . ' FROM ' . $this->source($source, 0);
            if ($definition->where !== null) {
                $query .= ' WHERE ' . $this->expression($definition->where, $source);
            }
        } else {
            $query = $source->query->toString();
        }

        return $with . self::CTE_NAME . ' (' . implode(', ', $names) . ') AS (' . $query . ') ';
    }

    /**
     * Emits ordinal ordering for a projection or compound result.
     *
     * @param list<Order> $orderBy
     */
    public function ordering(array $orderBy): string
    {
        if ($orderBy === []) {
            return '';
        }
        $terms = [];
        foreach ($orderBy as $term) {
            $terms[] = $term->position . ($term->descending ? ' DESC' : ' ASC') . ($term->nullsFirst ? ' NULLS FIRST' : ' NULLS LAST');
        }

        return ' ORDER BY ' . implode(', ', $terms);
    }

    /**
     * Emits the row bounds in the recorded operand order.
     */
    public function limit(Limit $limit): string
    {
        $count = $this->expression($limit->count, null);
        if ($limit->offset === null) {
            return 'LIMIT ' . $count;
        }

        $offset = $this->expression($limit->offset, null);

        return $limit->offsetFirst ? 'LIMIT ' . $offset . ', ' . $count : 'LIMIT ' . $count . ' OFFSET ' . $offset;
    }

    /**
     * Renders a checked expression occurrence, keeping its operand order.
     *
     * @param list<Join> $joins The remaining source occurrences
     */
    public function expression(
        Arithmetic|BetweenTest|BlobLiteral|CaseExpression|CastExpression|Coalesce|CollatedExpression|ColumnReference|Comparison|Concatenation|FoldedMembership|HexIntegerLiteral|IntegerLiteral|Logical|MembershipTest|NullLiteral|Parameter|PatternTest|RealLiteral|TextLiteral|TruthTest|UnaryExpression $expression,
        TableSource|LocalSource|DefinitionSource|null $source,
        array $joins = [],
    ): string {
        return match (true) {
            $expression instanceof ColumnReference => $this->column($expression, $source, $joins),
            $expression instanceof Coalesce => $this->coalesce($expression, $source, $joins),
            $expression instanceof Arithmetic, $expression instanceof Comparison, $expression instanceof Logical
                => '(' . $this->expression($expression->left, $source, $joins) . ' ' . $expression->operator->value . ' ' . $this->expression($expression->right, $source, $joins) . ')',
            $expression instanceof BetweenTest => $this->between($expression, $source, $joins),
            $expression instanceof FoldedMembership => $this->foldedMembership($expression),
            $expression instanceof MembershipTest => $this->membership($expression, $source, $joins),
            $expression instanceof PatternTest => $this->pattern($expression, $source, $joins),
            $expression instanceof CaseExpression => $this->choice($expression, $source, $joins),
            $expression instanceof Concatenation => '(' . $this->expression($expression->left, $source, $joins)
                . ' || ' . $this->expression($expression->right, $source, $joins) . ')',
            $expression instanceof CollatedExpression => '(' . $this->expression($expression->operand, $source, $joins) . ' COLLATE ' . $expression->collation->value . ')',
            $expression instanceof CastExpression => '(CAST(' . $this->expression($expression->operand, $source, $joins)
                . ' AS ' . ($expression->typeName?->quoted() ?? $expression->target->value) . '))',
            $expression instanceof TruthTest => $this->truth($expression, $source, $joins),
            $expression instanceof UnaryExpression => '(' . $expression->kind->value . ' ' . $this->expression($expression->operand, $source, $joins) . ')',
            $expression instanceof IntegerLiteral => $expression->decimal,
            $expression instanceof HexIntegerLiteral, $expression instanceof RealLiteral, $expression instanceof TextLiteral
                => $expression->toString(),
            $expression instanceof BlobLiteral => "X'" . $expression->hex . "'",
            $expression instanceof NullLiteral => 'NULL',
            $expression instanceof Parameter => '?' . $expression->label,
        };
    }

    /**
     * Writes the built-in test, preserving its lexical parameter order.
     * @param list<Join> $joins The joined scope
     */
    public function pattern(PatternTest $expression, TableSource|LocalSource|DefinitionSource|null $source, array $joins): string
    {
        $subject = $this->expression($expression->subject, $source, $joins);
        $pattern = $this->expression($expression->pattern, $source, $joins);
        $escape = $expression->escape === null ? '' : ' ESCAPE ' . $this->expression($expression->escape, $source, $joins);

        return '(' . $subject . ($expression->negated ? ' NOT ' : ' ') . $expression->kind->value . ' ' . $pattern . $escape . ')';
    }

    /**
     * Emits all arguments in their native evaluation and parameter order.
     * @param list<Join> $joins
     */
    public function coalesce(Coalesce $expression, TableSource|LocalSource|DefinitionSource|null $source, array $joins): string
    {
        $arguments = [];
        foreach ($expression->arguments as $argument) {
            $arguments[] = $this->expression($argument->expression, $source, $joins);
        }

        return ($expression->ifNull ? 'IFNULL(' : 'COALESCE(') . implode(', ', $arguments) . ')';
    }

    /**
     * Renders the column of a checked source occurrence.
     * @param list<Join> $joins The remaining source occurrences
     */
    public function column(ColumnReference $expression, TableSource|LocalSource|DefinitionSource|null $source, array $joins): string
    {
        $source = $expression->source === 0 ? $source : ($joins[$expression->source - 1]->source ?? null);
        Check::invariant(isset($source->columns[$expression->slot]), 'A complete SELECT has this source slot.');

        $name = $source->columns[$expression->slot]->quoted();

        return $source instanceof DefinitionSource ? $name : $this->alias($expression->source) . '.' . $name;
    }

    /**
     * Emits the decided value while registering every numbered marker in order.
     */
    public function foldedMembership(FoldedMembership $expression): string
    {
        if ($expression->bindings === []) {
            if ($this->preserveEmptyMembership) {
                return $expression->matches ? '(0 NOT IN ())' : '(0 IN ())';
            }

            return $expression->matches ? '1' : '0';
        }
        $branches = [];
        foreach ($expression->bindings as $binding) {
            $branches[] = ' WHEN ?' . $binding->label . ' THEN 0';
        }

        return '((CASE' . implode('', $branches) . ' END)' . ($expression->matches ? ' NOT IN ())' : ' IN ())');
    }

    /**
     * Emits membership syntax and visits every written candidate in order.
     * @param list<Join> $joins The remaining source occurrences
     */
    public function membership(MembershipTest $expression, TableSource|LocalSource|DefinitionSource|null $source, array $joins): string
    {
        $operand = $this->expression($expression->operand, $source, $joins);
        $members = [];
        foreach ($expression->items as $item) {
            $members[] = $this->expression($item->expression, $source, $joins);
        }

        return '(' . $operand . ($expression->negated ? ' NOT IN (' : ' IN (') . implode(', ', $members) . '))';
    }

    /**
     * Emits one tested scalar and its ordered inclusive bounds.
     * @param list<Join> $joins The remaining source occurrences
     */
    public function between(BetweenTest $expression, TableSource|LocalSource|DefinitionSource|null $source, array $joins): string
    {
        return '(' . $this->expression($expression->operand, $source, $joins)
            . ($expression->negated ? ' NOT BETWEEN ' : ' BETWEEN ')
            . $this->expression($expression->lower, $source, $joins) . ' AND '
            . $this->expression($expression->upper, $source, $joins) . ')';
    }

    /**
     * Emits a Boolean test whose result is independent of visible truth-word names.
     * @param list<Join> $joins The subsequent input occurrences
     */
    public function truth(TruthTest $expression, TableSource|LocalSource|DefinitionSource|null $source, array $joins): string
    {
        $value = $this->expression($expression->operand, $source, $joins);
        $condition = $expression->desired ? $value : '(NOT ' . $value . ')';
        [$matched, $unmatched] = $expression->negated ? ['0', '1'] : ['1', '0'];

        return '(CASE WHEN ' . $condition . ' THEN ' . $matched . ' ELSE ' . $unmatched . ' END)';
    }

    /**
     * Emits the native conditional form and evaluates the base at most once.
     *
     * @param list<Join> $joins The remaining source occurrences
     */
    public function choice(CaseExpression $expression, TableSource|LocalSource|DefinitionSource|null $source, array $joins): string
    {
        $sql = '(CASE';
        if ($expression->base !== null) {
            $sql .= ' ' . $this->expression($expression->base, $source, $joins);
        }
        foreach ($expression->branches as $branch) {
            $sql .= ' WHEN ' . $this->expression($branch->when, $source, $joins)
                . ' THEN ' . $this->expression($branch->then, $source, $joins);
        }
        if ($expression->otherwise !== null) {
            $sql .= ' ELSE ' . $this->expression($expression->otherwise, $source, $joins);
        }

        return $sql . ' END)';
    }

    /**
     * Renders a local or absolute external reference with its occurrence alias.
     */
    public function source(TableSource|LocalSource $source, int $occurrence): string
    {
        $name = $source instanceof LocalSource ? self::CTE_NAME : $source->schema->quoted() . '.' . $source->table->quoted();

        return $name . ' AS ' . $this->alias($occurrence);
    }

    /**
     * Gives each occurrence its own quoted name.
     */
    public function alias(int $occurrence): string
    {
        return $occurrence === 0 ? self::SOURCE_ALIAS : '"__ztd_source_' . $occurrence . '"';
    }
}
