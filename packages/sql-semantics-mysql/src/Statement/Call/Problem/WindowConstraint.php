<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Problem;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Call\Window\Constraints;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A fixed constraint of a window specification, independent of table contents.
 *
 * The owner retains the complete specification. Named-window context can supply
 * its label without retaining SQL or a parser node.
 *
 * @visibility public
 * @example Refusing a diagnostic without its stated constraint
 *     new \SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowConstraint(new \SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec(), true) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class WindowConstraint implements Diagnostic
{
    use Snapshot;

    /**
     * @param WindowSpec $window The owning specification
     * @param bool $positional Whether its ordering uses a position; otherwise its boundary kinds run backwards
     */
    public function __construct(public readonly WindowSpec $window, public readonly bool $positional)
    {
        Check::input($positional ? (new Constraints())->positional($window) : (new Constraints())->reversed($window), 'A window diagnostic retains the specification that breaks its stated constraint.');
    }

    /**
     * Describes the constraint independently of an enclosing window label.
     */
    public function message(): string
    {
        return $this->positional ? 'ORDER BY or PARTITION BY uses legacy position indication which is not supported, use expression.' : 'frame start or end is negative, NULL or of non-integral type';
    }
}
