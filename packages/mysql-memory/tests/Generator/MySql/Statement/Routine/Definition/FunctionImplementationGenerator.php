<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Definition;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes FunctionImplementation values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Definition\FunctionImplementation>
 */
final class FunctionImplementationGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Definition\FunctionImplementation> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\ExternalBody|\SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement|\SqlSemantics\Platform\MySql\Statement\Statement> $body Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\Characteristic>>|null $characteristics Generated constructor operand
     */
    public function __construct(ErisGenerator $body, ?ErisGenerator $characteristics = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Definition\FunctionImplementation {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Definition\FunctionImplementation(body: $parts[0], characteristics: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $body,
            $characteristics ?? Generators::constant([])
        ));
    }
}
