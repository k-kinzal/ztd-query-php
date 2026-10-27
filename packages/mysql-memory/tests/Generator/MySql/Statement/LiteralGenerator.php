<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Literal values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal>
 */
final class LiteralGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Literal> */
    use Composition;

    /**
     * @param non-empty-list<ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal>> $alternatives Concrete SQL forms satisfying this role
     */
    public function __construct(array $alternatives)
    {
        $this->delegate = \Tests\Generator\Operands::oneOf($alternatives);
    }
}
