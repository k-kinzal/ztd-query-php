<?php

declare(strict_types=1);

namespace SqlSemantics\Statement;

use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * An input relation that is one named table, view or common table, optionally renamed.
 *
 * @visibility public
 * @example Reading the name of a named input
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT a FROM t AS x');
 *     [$query->singleNamedInput()->name()->name->value, $query->singleNamedInput()->alias()?->value] // => ['t', 'x']
 */
interface NamedRelation extends Relation
{
    /**
     * Answers the relation name as the statement wrote it, decoded.
     */
    public function name(): QualifiedName;

    /**
     * Answers the correlation name that renames the occurrence, when one is given.
     */
    public function alias(): ?Name;
}
