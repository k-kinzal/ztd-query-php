<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Problem;

use SqlSemantics\Platform\MySql\Rules\Expression\Preparation\DeferredArguments;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A scalar-position refusal retaining its complete non-scalar dependencies.
 *
 * Earlier name and query conditions can precede the operand's intrinsic refusal.
 * The diagnostic carries semantic values without publishing an ordinary Scalar.
 *
 * @visibility public
 * @example Retaining a row without assigning it a scalar role
 *     $row = new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')]);
 *     (new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedScalar($row))->operand === $row // => true
 */
final class RefusedScalar implements Diagnostic
{
    use Snapshot;

    /**
     * @param RowOperand|DeferredOperand $operand The operand with its preparation dependencies
     */
    public function __construct(public readonly RowOperand|DeferredOperand $operand)
    {
    }

    /**
     * Describes the intrinsic refusal; dependencies can fail before it.
     */
    public function message(): string
    {
        return (new DeferredArguments())->problem([$this->operand])->message();
    }
}
