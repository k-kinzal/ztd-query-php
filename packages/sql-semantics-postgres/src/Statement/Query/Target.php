<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Node;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Shape\Field;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Shape\OpenStar;

/**
 * One item of a select list or a RETURNING list: an expression with an optional name, or a star.
 *
 * Mirrors PostgreSQL's `ResTarget` node. An item contributes one output
 * field, or as many as its star expands to.
 * Source: https://www.postgresql.org/docs/17/sql-select.html#SQL-SELECT-LIST.
 *
 * @visibility SqlSemantics
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
