<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Contract;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Mode values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Contract\Mode>
 */
final class ModeGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Contract\Mode> */
    use Composition;

    /**
     * @param non-empty-list<ErisGenerator<\SqlSemantics\Contract\Mode>> $alternatives Concrete SQL forms satisfying this role
     */
    public function __construct(array $alternatives)
    {
        $this->delegate = \Tests\Generator\Operands::oneOf($alternatives);
    }
}
