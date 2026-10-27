<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Contract;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Session values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Contract\Session>
 */
final class SessionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Contract\Session> */
    use Composition;

    /**
     * @param non-empty-list<ErisGenerator<\SqlSemantics\Contract\Session>> $alternatives Concrete SQL forms satisfying this role
     */
    public function __construct(array $alternatives)
    {
        $this->delegate = \Tests\Generator\Operands::oneOf($alternatives);
    }
}
