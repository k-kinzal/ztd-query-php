<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Reference\Column;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Resolution values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Reference\Column\Resolution>
 */
final class ResolutionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Reference\Column\Resolution> */
    use Composition;

    /**
     * @param non-empty-list<ErisGenerator<\SqlSemantics\Statement\Reference\Column\Resolution>> $alternatives Concrete SQL forms satisfying this role
     */
    public function __construct(array $alternatives)
    {
        $this->delegate = \Tests\Generator\Operands::oneOf($alternatives);
    }
}
