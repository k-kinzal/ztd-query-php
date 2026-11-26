<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Model\Statement\Fact;

use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\TableResolution;
use SqlSemantics\Platform\Sqlite\Model\Statement\Shape\RowShape;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * What an operation derived for one relation occurrence or one table use.
 *
 * @visibility SqlSemantics
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
