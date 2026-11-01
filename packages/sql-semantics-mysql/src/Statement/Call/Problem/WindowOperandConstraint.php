<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Problem;

use SqlSemantics\Platform\MySql\Rules\Expression\Preparation\DeferredArguments;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredWindowSpec;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A non-scalar item of a retained window specification.
 *
 * @visibility public
 * @example Keeping the window's complete ordering
 *     $row = new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral(), new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral()]);
 *     $window = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredWindowSpec(order: [new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredOrderItem($row)]);
 *     (new \SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowOperandConstraint($window))->window === $window // => true
 */
final class WindowOperandConstraint implements Diagnostic
{
    use Snapshot;

    /**
     * @param DeferredWindowSpec $window The complete specification containing the refused operand
     */
    public function __construct(public readonly DeferredWindowSpec $window)
    {
    }

    /**
     * Describes the retained operand's intrinsic constraint.
     */
    public function message(): string
    {
        return (new DeferredArguments())->problem([$this->window->operand()])->message();
    }
}
