<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Model\Statement\Fact;

use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\TableResolution;
use SqlSemantics\Platform\MySql\Model\Statement\Shape\RowShape;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

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
