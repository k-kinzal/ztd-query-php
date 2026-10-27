<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Operand values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Operand>
 */
final class OperandGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Operand> */
    use Composition;

    /**
     * @param non-empty-list<ErisGenerator<\SqlSemantics\Statement\Operand>> $alternatives Concrete SQL forms satisfying this role
     */
    public function __construct(array $alternatives)
    {
        $this->delegate = \Tests\Generator\Operands::oneOf($alternatives);
    }
}
