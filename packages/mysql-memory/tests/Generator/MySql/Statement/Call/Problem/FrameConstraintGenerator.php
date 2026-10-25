<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Problem;

use SqlSemantics\Platform\MySql\Statement\Call\Problem\FrameCondition;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\FrameConstraint as Model;
use SqlSemantics\Platform\MySql\Statement\Call\Window\Frame;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBound;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameUnit;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use Tests\Generator\Choices;
use Tests\Generator\Inhabitant;

/**
 * Generates a frame together with the contradiction its diagnostic must retain.
 */
final class FrameConstraintGenerator implements Inhabitant
{
    /**
     * Samples every declared condition without relying on rejection of unrelated frames.
     */
    public function sample(Choices $choices): Model
    {
        $condition = $choices->one(FrameCondition::cases());
        $offset = new NumberLiteral((string) $choices->integer(0, 20) . ($condition === FrameCondition::Offset ? '.5' : ''));
        $bound = new FrameBound(FrameBoundKind::Preceding, $offset, $condition === FrameCondition::RowsInterval ? $choices->one(IntervalUnit::cases()) : null);
        $end = $choices->integer(0, 1) === 0 ? null : new FrameBound(FrameBoundKind::CurrentRow);
        $frame = new Frame($condition === FrameCondition::RangeOrder ? FrameUnit::Range : FrameUnit::Rows, $bound, $end);

        return new Model(new WindowSpec(frame: $frame), $condition);
    }
}
