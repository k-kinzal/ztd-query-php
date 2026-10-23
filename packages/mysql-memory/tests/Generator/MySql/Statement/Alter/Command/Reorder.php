<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Alter\Command;

use SqlSemantics\Platform\MySql\Statement\Alter\Command\Reorder as Model;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Direction;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\Choices;
use Tests\Generator\Inhabitant;

/**
 * ALTER TABLE ordering admits column references rather than arbitrary scalars.
 */
final class Reorder implements Inhabitant
{
    /**
     * Samples a nonempty sequence of column names and optional directions.
     */
    public function sample(Choices $choices): Model
    {
        $items = [];
        $count = $choices->integer(1, 3);
        for ($index = 0; $index < $count; $index++) {
            $items[] = new OrderItem(new ColumnUse(new Name($choices->one(['a', 'b', 'column name', '列']))), $choices->one([null, ...Direction::cases()]));
        }
        return new Model($items);
    }
}
