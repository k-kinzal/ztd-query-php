<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnReference;

/**
 * One bound of a window frame.
 *
 * An offset that is the bare column `unbounded` is written quoted: the
 * grammar reads an unquoted `unbounded` before PRECEDING or FOLLOWING as the
 * keyword of an unbounded frame (`%nonassoc UNBOUNDED` in gram.y).
 * Source: https://www.postgresql.org/docs/17/sql-expressions.html#SYNTAX-WINDOW-FUNCTIONS.
 *
 * @visibility SqlSemantics
 * @example Reading an offset bound
 *     $bound = new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\FrameBound(
 *         \SqlSemantics\Platform\PostgreSql\Statement\Invocation\FrameBoundKind::OffsetPreceding,
 *         new \SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('2')),
 *     );
 *     $bound->offset->value->digits // => '2'
 * @example Rejecting an offset on CURRENT ROW
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\FrameBound(\SqlSemantics\Platform\PostgreSql\Statement\Invocation\FrameBoundKind::CurrentRow, new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral()) // throws \SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction
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
        $offset = $this->offset;
        if ($offset instanceof ColumnReference && count($offset->parts) === 1 && $offset->parts[0]->value === 'unbounded') {
            $out->name($offset->parts[0], NameUse::Identifier);
        } else {
            $out->node($offset);
        }
        $out->keyword(...explode(' ', $this->kind->value));
    }
}
