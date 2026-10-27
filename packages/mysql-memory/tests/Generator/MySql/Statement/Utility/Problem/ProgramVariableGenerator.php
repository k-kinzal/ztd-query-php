<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ProgramVariable values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Problem\ProgramVariable>
 */
final class ProgramVariableGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Utility\Problem\ProgramVariable> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $name Generated constructor operand
     */
    public function __construct(ErisGenerator $name)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Utility\Problem\ProgramVariable {
            return new \SqlSemantics\Platform\MySql\Statement\Utility\Problem\ProgramVariable(name: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $name
        ));
    }
}
