<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Fact;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Warning values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Fact\Warning>
 */
final class WarningGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Fact\Warning> */
    use Composition;

    /**
     * @param non-empty-list<ErisGenerator<\SqlSemantics\Statement\Fact\Warning>> $alternatives Concrete SQL forms satisfying this role
     */
    public function __construct(array $alternatives)
    {
        $this->delegate = \Tests\Generator\Operands::oneOf($alternatives);
    }
}
