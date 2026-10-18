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
        if (!$exists && $query instanceof M\Select && ($query->from === null || $query->from instanceof Dual) && $query->items[0] instanceof M\Star) {
            return new M\Problem\Misuse(M\Problem\MisuseRule::StarWithoutTables);
        }
        if (!$exists && $query instanceof M\Select) {
            foreach ($query->items as $item) {
                if ($item instanceof \SqlSemantics\Platform\MySql\Statement\Name\TableWildcard && $item->table->name->value === '') {
                    return new M\Problem\Misuse(M\Problem\MisuseRule::StarWithoutTables);
                }
            }
        }
        return null;
    }

    /**
     * A read row supplies at least one expression and the same width as its siblings.
     */
    public function rows(M\ValuesQuery $query): ?Diagnostic
    {
        $width = count($query->rows[0]->values);
        foreach ($query->rows as $index => $row) {
            $count = count($row->values);
            if ($count !== $width) {
                return new M\Problem\CountMismatch(M\Problem\CountedList::ValueRows, $width, $count, $index + 1, $row);
            }
            if ($count === 0) {
                return new M\Problem\Misuse(M\Problem\MisuseRule::EmptyValuesRow, valueRow: $row);
            }
            foreach ($row->values as $value) {
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
