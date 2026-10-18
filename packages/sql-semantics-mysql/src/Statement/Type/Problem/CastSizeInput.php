<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Type\Problem;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * An intrinsic cast-size refusal with the input boundary of its diagnostic label.
 *
 * Only byte offsets accompany the typed bound. No SQL or parser node is retained.
 * Input adapters format the label from their request context; accepted scalar
 * structures never depend on these offsets or on request text.
 *
 * @visibility public
 * @example Reading an input refusal without its original SQL
 *     (new \SqlSemantics\Platform\MySql\Statement\Type\Problem\CastSizeInput(new \SqlSemantics\Platform\MySql\Statement\Type\Problem\InvalidTypeSize(\SqlSemantics\Platform\MySql\Statement\Type\Problem\TypeLimit::Precision, '', 66, 65), 18, 22))->problem->actual // => 66
 */
final class CastSizeInput implements Diagnostic
{
    use Snapshot;

    /**
     * @param InvalidTypeSize $problem The violated size bound, before input-label formatting
     * @param int $start The operand's first byte offset in the input adapter's request
     * @param int $end The exclusive end of that operand
     */
    public function __construct(public readonly InvalidTypeSize $problem, public readonly int $start, public readonly int $end)
    {
        Check::input($start >= 0 && $end >= $start, 'A diagnostic input boundary is ordered and nonnegative.');
        Check::input($problem->name === '', 'An input-dependent label is held by its request context.');
    }

    /**
     * Describes the bound without requiring input spelling.
     */
    public function message(): string
    {
        return $this->problem->message();
    }
}
