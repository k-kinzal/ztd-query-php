<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes InvalidNamedConstant values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Problem\InvalidNamedConstant>
 */
final class InvalidNamedConstantGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Call\Problem\InvalidNamedConstant> */
    use Composition;

    /**
     * @param ErisGenerator<bool>|null $nullName Generated constructor operand
     */
    public function __construct(?ErisGenerator $nullName = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Call\Problem\InvalidNamedConstant {
            return new \SqlSemantics\Platform\MySql\Statement\Call\Problem\InvalidNamedConstant(nullName: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $nullName ?? Generators::constant(false)
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Problem\InvalidNamedConstant>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Call\\Problem\\InvalidNamedConstant' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new InvalidNamedConstantGenerator(
            nullName: Generators::constant(true)
        )];
    }

}
