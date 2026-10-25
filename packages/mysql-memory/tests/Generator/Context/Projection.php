<?php

declare(strict_types=1);

namespace Tests\Generator\Context;

use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use Tests\Generator\Choices;

/**
 * Generates query operands with explicit compatible widths and optional local limits.
 */
final class Projection
{
    /**
     * @param positive-int $width The number of explicit output columns
     */
    public function sample(Choices $choices, int $width = 1, bool $limited = false): Select
    {
        $items = array_map(static fn (): SelectExpression => new SelectExpression(new NumberLiteral((string) $choices->integer(0, 1000))), range(1, $width));
        return new Select([], $items, limit: $limited ? new RowLimit(new NumberLiteral((string) $choices->integer(0, 10))) : null);
    }
}
