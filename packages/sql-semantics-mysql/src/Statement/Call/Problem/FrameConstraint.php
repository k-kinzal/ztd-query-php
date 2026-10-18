<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Problem;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Call\Window\FrameConstraints;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * Retains the complete specification responsible for a fixed frame condition.
 *
 * @visibility public
 * @example Refusing a frame diagnostic without its stated condition
 *     new \SqlSemantics\Platform\MySql\Statement\Call\Problem\FrameConstraint(new \SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec(), \SqlSemantics\Platform\MySql\Statement\Call\Problem\FrameCondition::RowsInterval) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class FrameConstraint implements Diagnostic
{
    use Snapshot;

    /**
     * @param WindowSpec $window The complete written frame and ordering
     * @param FrameCondition $condition Its fixed contradiction
     */
    public function __construct(public readonly WindowSpec $window, public readonly FrameCondition $condition)
    {
        Check::input((new FrameConstraints())->condition($window) === $condition, 'A frame diagnostic retains its stated contradiction.');
    }

    /**
     * Describes the relationship without depending on an enclosing window name.
     */
    public function message(): string
    {
        return $this->condition->value;
    }
}
