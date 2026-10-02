<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * One bound of a window frame.
 *
 * Source: https://www.postgresql.org/docs/17/sql-expressions.html#SYNTAX-WINDOW-FUNCTIONS.
 *
 * @visibility public
 * @example Reading an offset bound
 *     $bound = new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\FrameBound(
 *         \SqlSemantics\Platform\PostgreSql\Statement\Invocation\FrameBoundKind::OffsetPreceding,
 *         new \SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('2')),
 *     );
 *     $bound->offset->value->digits // => '2'
 * @example Rejecting an offset on CURRENT ROW
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\FrameBound(\SqlSemantics\Platform\PostgreSql\Statement\Invocation\FrameBoundKind::CurrentRow, new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral()) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class FrameBound implements Clause
{
    use Snapshot;

    /**
     * @param FrameBoundKind $kind Where the bound lies
     * @param Scalar|null $offset The offset expression; given exactly for the offset kinds
     */
    public function __construct(public readonly FrameBoundKind $kind, public readonly ?Scalar $offset = null)
    {
        Check::input($kind->offset() === ($offset !== null), 'A frame bound has an offset exactly when it is an offset bound.');
    }

    /**
     * Derives the offset expression.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        if ($this->offset !== null) {
            $derivation->scalar($this->offset, $environment);
        }
    }

    /**
     * Writes the offset and the keywords.
     */
    public function render(Output $out): void
    {
        $out->node($this->offset)->keyword(...explode(' ', $this->kind->value));
    }
}
