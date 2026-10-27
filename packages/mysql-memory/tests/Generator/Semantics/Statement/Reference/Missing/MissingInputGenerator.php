<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Reference\Missing;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes MissingInput values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Reference\Missing\MissingInput>
 */
final class MissingInputGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Reference\Missing\MissingInput> */
    use Composition;

    /**
     * @param non-empty-list<ErisGenerator<\SqlSemantics\Statement\Reference\Missing\MissingInput>> $alternatives Concrete SQL forms satisfying this role
     */
    public function __construct(array $alternatives)
    {
        $this->delegate = \Tests\Generator\Operands::oneOf($alternatives);
    }
}
