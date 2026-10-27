<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Type;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes TypeDescriptor values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Type\TypeDescriptor>
 */
final class TypeDescriptorGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Type\TypeDescriptor> */
    use Composition;

    /**
     * @param non-empty-list<ErisGenerator<\SqlSemantics\Statement\Type\TypeDescriptor>> $alternatives Concrete SQL forms satisfying this role
     */
    public function __construct(array $alternatives)
    {
        $this->delegate = \Tests\Generator\Operands::oneOf($alternatives);
    }
}
