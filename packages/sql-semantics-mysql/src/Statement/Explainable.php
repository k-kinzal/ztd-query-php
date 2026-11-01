<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement;

/**
 * A query or data manipulation statement whose plan EXPLAIN can inspect.
 *
 * SELECT, TABLE, VALUES, INSERT, REPLACE, UPDATE and DELETE have this role.
 * It describes a statement position, independently of whether the named
 * objects exist, whether privileges permit access, or whether execution succeeds.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/explain.html.
 *
 * @visibility public
 * @example The plan of a row query can be explained
 *     new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))]) instanceof \SqlSemantics\Platform\MySql\Statement\Explainable // => true
 */
interface Explainable extends Statement
{
}
