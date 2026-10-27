<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Type;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Invalid values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Type\Invalid>
 */
final class InvalidGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Type\Invalid> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\Diagnostic> $cause Generated constructor operand
     */
    public function __construct(ErisGenerator $cause)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Statement\Type\Invalid {
            return new \SqlSemantics\Statement\Type\Invalid(cause: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $cause
        ));
    }
}
