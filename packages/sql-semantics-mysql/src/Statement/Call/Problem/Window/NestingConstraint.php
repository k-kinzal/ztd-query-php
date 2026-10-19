<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Problem\Window;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Call\Window\Nesting;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A specification containing a window call in its own partition or order scope.
 *
 * @visibility public
 * @example Refusing a nesting diagnostic for an empty specification
 *     new \SqlSemantics\Platform\MySql\Statement\Call\Problem\Window\NestingConstraint(new \SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec()) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class NestingConstraint implements Diagnostic
{
    use Snapshot;

    /**
     * @param WindowSpec $window The complete specification owning the nested call
     */
    public function __construct(public readonly WindowSpec $window)
    {
        Check::input((new Nesting())->contains($window), 'A window nesting diagnostic retains its nested call in the same expression scope.');
    }

    /**
     * Describes the expression-scope contradiction without an enclosing window label.
     */
    public function message(): string
    {
        return 'A window specification cannot contain a window function in its own expression scope.';
    }
}
