<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Type;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes TypeName values and preserves their operand generators when shrinking.
 *
 * @template T of \SqlSemantics\Platform\MySql\Statement\Type\TypeName
 * @implements ErisGenerator<T>
 */
final class TypeNameGenerator implements ErisGenerator
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
