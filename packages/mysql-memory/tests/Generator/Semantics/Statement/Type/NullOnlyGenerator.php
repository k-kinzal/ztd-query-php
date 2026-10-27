<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Type;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes NullOnly values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Type\NullOnly>
 */
final class NullOnlyGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Type\NullOnly> */
    use Composition;

    /**
     * Constructs the operand with no additional inputs.
     */
    public function __construct()
    {
        $this->delegate = Generators::map(static fn (bool $unused): \SqlSemantics\Statement\Type\NullOnly => new \SqlSemantics\Statement\Type\NullOnly(), Generators::constant(false));
    }
}
