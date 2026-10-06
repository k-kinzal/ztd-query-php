<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OpenStar;

/**
 * One item of a select list or a RETURNING list: an expression with an optional name, or a star.
 *
 * Mirrors PostgreSQL's `ResTarget` node. An item contributes one output
 * field, or as many as its star expands to.
 * Source: https://www.postgresql.org/docs/17/sql-select.html#SQL-SELECT-LIST.
 *
 * @visibility public
 * @example Reading the alias of a select-list item
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT 1 AS one');
 *     $query->statement->targets[0]->alias->value // => 'one'
 */
interface Target extends Node
{
    /**
     * Derives the item and answers the output fields it contributes, numbered from a position.
     *
     * @return list<Field|OpenStar>
     */
    public function project(Derivation $derivation, Environment $environment, int $position): array;
}
