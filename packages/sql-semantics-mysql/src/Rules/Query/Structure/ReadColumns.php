<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Structure;

use SqlSemantics\Platform\MySql\Rules\Expression\Shape\QueryWidth;
use SqlSemantics\Platform\MySql\Statement\Dml\DefaultRequest;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteMisuse;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteRule;
use SqlSemantics\Platform\MySql\Statement\Query as M;
use SqlSemantics\Platform\MySql\Statement\Relation\Dual;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Query;

/**
 * Intrinsic column constraints of a query used for reading.
 *
 * Query bodies are also templates for unused CTEs and stored instructions, and
 * VALUES can supply INSERT defaults. These constraints belong to their read
 * positions, independently of a catalog or execution eligibility judgement.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ReadColumns
{
    /**
     * Finds a fixed contradiction in the written output, leaving catalog widths open.
     * EXISTS permits a bare star without tables because it does not read its columns.
     * @param array<string, list<\SqlSemantics\Statement\Identifier\Name|null>|null> $common Enclosing common-table outputs
     */
    public function problem(Query|M\Set\LeadingUnion $query, bool $exists = false, array $common = []): ?Diagnostic
    {
        $position = $query instanceof Query ? (new \SqlSemantics\Platform\MySql\Rules\Query\SortScopes())->problem($query, $exists, $common) : null;
        $template = $position ?? $this->templates($query, $common, $exists);
        if ($template !== null) {
            return $template;
        }
        if ($query instanceof M\ParenthesizedQuery || $query instanceof M\QueryStatement) {
            return $this->problem($query->query, $exists, $common);
        }
        if ($query instanceof M\QueryExpression) {
            return $this->problem($query->body, $exists, (new \SqlSemantics\Platform\MySql\Rules\Expression\Shape\QueryColumns())->common($query->with, $common));
        }
        if ($query instanceof M\Set\SetOperation || $query instanceof M\Set\OrderedSetOperation || $query instanceof M\Set\LeadingUnion) {
            return $this->problem($query->left, $exists, $common) ?? $this->problem($query->right, $exists, $common) ?? $this->combined($query->left, $query->right, $exists, $common);
        }
        if ($query instanceof M\ValuesQuery) {
            return $this->rows($query);
        }
        return !$exists && $query instanceof M\Select ? $this->projection($query) : null;
    }

    /**
     * Checks stars whose written qualifier or missing input is intrinsically contradictory.
     */
    public function projection(M\Select $query): ?Diagnostic
    {
        if (($query->from === null || $query->from instanceof Dual) && $query->items[0] instanceof M\Star) {
            return new M\Problem\Misuse(M\Problem\MisuseRule::StarWithoutTables);
        }
        foreach ($query->items as $item) {
            if ($item instanceof \SqlSemantics\Platform\MySql\Statement\Name\TableWildcard && $item->table->name->value === '') {
                return new M\Problem\Misuse(M\Problem\MisuseRule::StarWithoutTables);
            }
            if ($item instanceof \SqlSemantics\Platform\MySql\Statement\Name\TableWildcard && ($query->from === null || $query->from instanceof Dual)) {
                return new M\Problem\UnknownQualifier($item->table);
            }
        }
        return null;
    }

    /**
     * Finds deferred bodies only along the read's lexical dependency graph.
     * @param array<string, list<\SqlSemantics\Statement\Identifier\Name|null>|null> $common Enclosing common-table outputs
     */
    public function templates(Query|M\Set\LeadingUnion $query, array $common = [], bool $exists = false): ?Diagnostic
    {
        $recursion = (new \SqlSemantics\Platform\MySql\Rules\Query\From\InputNames())->problem($query) ?? (new \SqlSemantics\Platform\MySql\Rules\Query\Recursion\Constraints())->problem($query);
        if ($recursion !== null) {
            return $recursion;
        }
        foreach ((new \SqlSemantics\Platform\MySql\Rules\Expression\Shape\QueryScopes())->nodes($query, $exists, $common) as [$node, $scope, $existence]) {
            $problem = $this->template($node, $scope, $existence);
            if ($problem !== null) {
                return $problem;
            }
        }
        return null;
    }

    /**
     * Checks one reached template in its own lexical scope and read position.
     * @param array<string, list<\SqlSemantics\Statement\Identifier\Name|null>|null> $common Enclosing common-table outputs
     */
    public function template(\SqlSemantics\Statement\Node $node, array $common, bool $exists): ?Diagnostic
    {
        $problem = match (true) {
            $node instanceof \SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable, $node instanceof M\With\CommonTableExpression => $this->input($node->query, $node->columns, $common),
            $node instanceof \SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable && ($node->on instanceof \SqlSemantics\Platform\MySql\Statement\Expression\RowOperand || $node->on instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand) => (new \SqlSemantics\Platform\MySql\Rules\Expression\Preparation\DeferredArguments())->problem([$node->on]),
            $node instanceof \SqlSemantics\Platform\MySql\Statement\Relation\JsonTable => (new \SqlSemantics\Platform\MySql\Rules\Query\From\JsonDocument())->problem($node) ?? (new \SqlSemantics\Platform\MySql\Rules\Query\From\JsonColumns())->problem($node->columns),
            $node instanceof M\Select => $this->windows($node),
            $node instanceof M\Set\SetOperation, $node instanceof M\Set\OrderedSetOperation, $node instanceof M\Set\LeadingUnion => $this->combined($node->left, $node->right, $exists, $common),
            $node instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredOrderItem => (new \SqlSemantics\Platform\MySql\Rules\Expression\Preparation\DeferredArguments())->problem([$node->expression]),
            $node instanceof M\With\DeferredBody, $node instanceof M\With\DeferredValuesQuery => $node->problem(),
            $node instanceof \SqlSemantics\Platform\MySql\Statement\Call\WindowSpecification => (new \SqlSemantics\Platform\MySql\Rules\Call\Window\Constraints())->problem($node, false),
            default => null,
        };
        return $problem ?? (new \SqlSemantics\Platform\MySql\Rules\Expression\Shape\ScopedOperands())->problem($node, $common);
    }

    /**
     * Applies literal frame constraints only to named windows used in this block.
     * Nested queries own their names and unused definitions retain inert offsets.
     */
    public function windows(M\Select $query): ?Diagnostic
    {
        $namesProblem = (new \SqlSemantics\Platform\MySql\Rules\Query\Window\Names())->problem($query);
        if ($namesProblem !== null) {
            return $namesProblem;
        }
        $dependency = (new \SqlSemantics\Platform\MySql\Rules\Query\Window\Dependencies())->problem($query);
        if ($dependency !== null) {
            return $dependency;
        }
        $parts = [...$query->items, ...$query->orderBy, ...array_values(array_filter([$query->where, $query->groupBy, $query->having, $query->qualify, $query->late]))];
        $names = array_map(static fn ($name): string => strtolower($name->value), (new \SqlSemantics\Platform\MySql\Rules\Query\WindowReferences())->names($parts, false));
        foreach ($query->windows as $definition) {
            if (in_array(strtolower($definition->name->value), $names, true)) {
                $problem = (new \SqlSemantics\Platform\MySql\Rules\Call\Window\Constraints())->problem($definition->specification);
                if ($problem !== null) {
                    return $problem;
                }
            }
        }
        return null;
    }

    /**
     * A read row supplies at least one expression and the same width as its siblings.
     */
    public function rows(M\ValuesQuery|M\With\DeferredValuesQuery $query): ?Diagnostic
    {
        $width = count($query->rows[0]->values);
        foreach ($query->rows as $index => $row) {
            $count = count($row->values);
            if ($count !== $width) {
                return new M\Problem\CountMismatch(M\Problem\CountedList::ValueRows, $width, $count, $index + 1, $row);
            }
            if ($count === 0 && $row instanceof M\ValueRow) {
                return new M\Problem\Misuse(M\Problem\MisuseRule::EmptyValuesRow, valueRow: $row);
            }
            foreach ($row->values as $value) {
                if ($value instanceof \SqlSemantics\Platform\MySql\Statement\Expression\RowOperand || $value instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand) {
                    return (new \SqlSemantics\Platform\MySql\Rules\Expression\Preparation\DeferredArguments())->problem([$value]);
                }
                if ($value instanceof DefaultRequest) {
                    return new WriteMisuse(WriteRule::DefaultOutsideInsert);
                }
            }
        }
        return null;
    }

    /**
     * Checks the read body before its explicitly renamed output columns.
     *
     * @param list<\SqlSemantics\Statement\Identifier\Name> $columns Renamed output columns
     * @param array<string, list<\SqlSemantics\Statement\Identifier\Name|null>|null> $common Enclosing common-table outputs
     */
    public function input(Query $query, array $columns, array $common = []): ?Diagnostic
    {
        return $this->problem($query, common: $common) ?? $this->named($query, $columns, $common);
    }

    /**
     * Requires an explicit relation column list to agree with a fixed query width.
     *
     * @param list<\SqlSemantics\Statement\Identifier\Name> $columns Renamed output columns
     * @param array<string, list<\SqlSemantics\Statement\Identifier\Name|null>|null> $common Enclosing common-table outputs
     */
    public function named(Query $query, array $columns, array $common = []): ?M\Problem\CountMismatch
    {
        $width = (new QueryWidth())->of($query, common: $common);
        return $columns !== [] && $width !== null && count($columns) !== $width ? new M\Problem\CountMismatch(M\Problem\CountedList::DerivedColumns, count($columns), $width) : null;
    }

    /**
     * Compares fixed output widths; unknown declarations remain a resolution dependency.
     * @param array<string, list<\SqlSemantics\Statement\Identifier\Name|null>|null> $common Enclosing common-table outputs
     */
    public function combined(Query|M\Set\LeadingUnion $left, Query $right, bool $exists = false, array $common = []): ?Diagnostic
    {
        $one = (new QueryWidth())->of($left, $exists, $common);
        $two = (new QueryWidth())->of($right, $exists, $common);
        return $one !== null && $two !== null && $one !== $two ? new M\Problem\CountMismatch(M\Problem\CountedList::SetOperands, $one, $two) : null;
    }
}
