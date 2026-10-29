<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Contract;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Dialect values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Contract\Dialect>
 */
final class DialectGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Contract\Dialect> */
    use Composition;

    /**
     * @param non-empty-list<ErisGenerator<\SqlSemantics\Contract\Dialect>> $alternatives Concrete SQL forms satisfying this role
     */
    public function __construct(array $alternatives)
    {
        $this->delegate = Generators::oneOf(...$alternatives);
    }
}
