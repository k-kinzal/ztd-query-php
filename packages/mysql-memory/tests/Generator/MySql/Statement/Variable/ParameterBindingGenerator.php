<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Variable;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ParameterBinding values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Variable\ParameterBinding>
 */
final class ParameterBindingGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Variable\ParameterBinding> */
    use Composition;

    /**
     * @param ErisGenerator<int> $position Generated constructor operand
     */
    public function __construct(ErisGenerator $position)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Variable\ParameterBinding {
            return new \SqlSemantics\Platform\MySql\Statement\Variable\ParameterBinding(position: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $position
        ));
    }
}
