<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression;

use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Statement\Identifier\Name;

/**
 * One step applied to a value or an assignment target: a field selection, a subscript, a slice or a star.
 *
 * Mirrors the items of PostgreSQL's `A_Indirection` node. Steps follow an
 * expression in parentheses or a parameter, and the column of an INSERT or
 * UPDATE target.
 * Source: https://www.postgresql.org/docs/17/sql-expressions.html#SQL-EXPRESSIONS-SUBSCRIPTS,
 * https://www.postgresql.org/docs/17/sql-expressions.html#FIELD-SELECTION.
 *
 * @visibility public
 * @example Reading the field a step selects
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Expression\FieldSelection(new \SqlSemantics\Statement\Identifier\Name('city')))->field()->value // => 'city'
 */
interface IndirectionStep extends Clause
{
    /**
     * Answers the field name when the step selects a field, or null for a subscript, a slice or a star.
     */
    public function field(): ?Name;
}
