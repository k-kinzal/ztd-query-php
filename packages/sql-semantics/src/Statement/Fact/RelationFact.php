<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Fact;

use SqlSemantics\Statement\Reference\Table\TableResolution;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Snapshot;

/**
 * What an operation derived for one relation occurrence or one table use.
 *
 * @visibility public
 * @example Reading the shape of a named input
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $table = $semantics->analyze('CREATE TABLE t (a INTEGER, b TEXT)');
 *     $query = $semantics->analyze('SELECT a FROM t', [$table]);
 *     count($query->facts->relation($query->inputRelation())->shape->slots) // => 2
 */
final class RelationFact
{
    use Snapshot;

    /**
     * @param RowShape $shape The ordered output positions the occurrence contributes
     * @param TableResolution|null $table The resolution of the relation name, when the occurrence names one
     */
    public function __construct(public readonly RowShape $shape, public readonly ?TableResolution $table = null)
    {
    }
}
