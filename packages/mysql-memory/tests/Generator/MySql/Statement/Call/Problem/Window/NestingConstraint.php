<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Problem\Window;

use SqlSemantics\Platform\MySql\Statement\Call\Problem\Window\NestingConstraint as Model;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use Tests\Generator\Choices;
use Tests\Generator\Inhabitant;

/**
 * A nesting diagnostic retains a window call in its own partition or ordering scope.
 */
final class NestingConstraint implements Inhabitant
{
    /**
     * Samples both forbidden expression scopes with argument-free window functions.
     */
    public function sample(Choices $choices): Model
    {
        $call = new WindowFunction($choices->one([WindowFunctionKind::RowNumber, WindowFunctionKind::Rank, WindowFunctionKind::DenseRank]), [], new WindowSpec());
        $item = new OrderItem($call);
        return new Model($choices->integer(0, 1) === 0 ? new WindowSpec(partition: [$item]) : new WindowSpec(order: [$item]));
    }
}
