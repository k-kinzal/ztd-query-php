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
     */
    public function problem(Query|M\Set\LeadingUnion $query, bool $exists = false): ?Diagnostic
    {
        $template = $this->templates($query);
        if ($template !== null) {
            return $template;
        }
        if ($query instanceof M\ParenthesizedQuery || $query instanceof M\QueryStatement) {
            return $this->problem($query->query, $exists);
        }
        if ($query instanceof M\QueryExpression) {
            return $this->problem($query->body, $exists);
        }
        if ($query instanceof M\Set\SetOperation || $query instanceof M\Set\OrderedSetOperation || $query instanceof M\Set\LeadingUnion) {
            return $this->problem($query->left, $exists) ?? $this->problem($query->right, $exists) ?? $this->combined($query->left, $query->right, $exists);
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
     */
    public function templates(Query|M\Set\LeadingUnion $query): ?Diagnostic
    {
        $recursion = (new \SqlSemantics\Platform\MySql\Rules\Query\Recursion\Constraints())->problem($query);
        if ($recursion !== null) {
            return $recursion;
        }
        foreach ((new ReadDependencies())->nodes($query) as $node) {
            if ($node instanceof \SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable || $node instanceof M\With\CommonTableExpression) {
                $problem = $this->problem($node->query);
                if ($problem !== null) {
                    return $problem;
                }
            }
            if ($node instanceof \SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable && ($node->on instanceof \SqlSemantics\Platform\MySql\Statement\Expression\RowOperand || $node->on instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand)) {
                return (new \SqlSemantics\Platform\MySql\Rules\Expression\Preparation\DeferredArguments())->problem([$node->on]);
            }
            if ($node instanceof \SqlSemantics\Platform\MySql\Statement\Relation\JsonTable) {
                $problem = (new \SqlSemantics\Platform\MySql\Rules\Query\From\JsonColumns())->problem($node->columns);
                if ($problem !== null) {
                    return $problem;
                }
            }
            if ($node instanceof M\Select) {
                $problem = $this->windows($node);
                if ($problem !== null) {
                    return $problem;
                }
            }
            if ($node instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredOrderItem) {
                return (new \SqlSemantics\Platform\MySql\Rules\Expression\Preparation\DeferredArguments())->problem([$node->expression]);
            }
            if ($node instanceof M\With\DeferredBody || $node instanceof M\With\DeferredValuesQuery) {
                return $node->problem();
            }
            if ($node instanceof \SqlSemantics\Platform\MySql\Statement\Call\WindowSpecification) {
                $problem = (new \SqlSemantics\Platform\MySql\Rules\Call\Window\Constraints())->problem($node, false);
                if ($problem !== null) {
                    return $problem;
                }
            }
        }
        return null;
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
     * Compares fixed output widths; unknown declarations remain a resolution dependency.
     */
    public function combined(Query|M\Set\LeadingUnion $left, Query $right, bool $exists = false): ?Diagnostic
    {
        $one = (new QueryWidth())->of($left, $exists);
        $two = (new QueryWidth())->of($right, $exists);
        return $one !== null && $two !== null && $one !== $two ? new M\Problem\CountMismatch(M\Problem\CountedList::SetOperands, $one, $two) : null;
    }
}
