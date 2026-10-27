<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Selection values and preserves their operand generators when shrinking.
 *
 * @template T of \SqlSemantics\Statement\Selection
 * @implements ErisGenerator<T>
 */
final class SelectionGenerator implements ErisGenerator
{
    /** @use Composition<T> */
    use Composition;

    /**
     * @param non-empty-list<ErisGenerator<T>> $alternatives Concrete SQL forms satisfying this role
     */
    public function __construct(array $alternatives)
    {
        $this->delegate = \Tests\Generator\Operands::oneOf($alternatives);
    }
}
