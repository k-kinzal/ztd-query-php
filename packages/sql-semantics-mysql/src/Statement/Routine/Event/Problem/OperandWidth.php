<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Event\Problem;

use SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns;
use SqlSemantics\Platform\MySql\Statement\Routine\Event\Clause;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A refused schedule operand, with its clause and whether the whole operand is a row.
 *
 * Legacy schedules evaluate a refused operand again while converting its value.
 * Keeping its role allows consumers to report those following conditions without
 * reparsing the request. This is an input diagnostic, not an accepted expression.
 * Observed on MySQL 5.6.51 and 5.7.44.
 *
 * @visibility public
 * @example Keeping the operand role alongside its width condition
 *     (new \SqlSemantics\Platform\MySql\Statement\Routine\Event\Problem\OperandWidth(new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns(1, 2), \SqlSemantics\Platform\MySql\Statement\Routine\Event\Clause::At, true))->rowValue // => true
 */
final class OperandWidth implements Diagnostic
{
    use Snapshot;

    /**
     * @param OperandColumns $problem The incompatible scalar width
     * @param Clause $clause The schedule value's role
     * @param bool $rowValue Whether the whole value, rather than a nested operand, is a row
     */
    public function __construct(public readonly OperandColumns $problem, public readonly Clause $clause, public readonly bool $rowValue)
    {
    }

    /**
     * Describes the scalar width mismatch.
     */
    public function message(): string
    {
        return $this->problem->message();
    }
}
