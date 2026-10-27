<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query\Problem\Operand;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes UnenclosedUnion values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\UnenclosedUnion>
 */
final class UnenclosedUnionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\UnenclosedUnion> */
    use Composition;

    /**
     * @param ErisGenerator<int|null>|null $offset Generated constructor operand
     */
    public function __construct(?ErisGenerator $offset = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\UnenclosedUnion {
            return new \SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\UnenclosedUnion(offset: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $offset ?? Generators::constant(null)
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\UnenclosedUnion>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Query\\Problem\\Operand\\UnenclosedUnion' => [\SqlSemantics\Contract\GrammarRelease::MySql5651, new UnenclosedUnionGenerator(
            offset: Generators::constant(null)
        )];
    }

}
