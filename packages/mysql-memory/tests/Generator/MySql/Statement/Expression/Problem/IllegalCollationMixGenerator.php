<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Expression\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes IllegalCollationMix values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Problem\IllegalCollationMix>
 */
final class IllegalCollationMixGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Expression\Problem\IllegalCollationMix> */
    use Composition;

    /**
     * @param ErisGenerator<list<array{string, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility}>> $operands Generated constructor operand
     * @param ErisGenerator<string> $operation Generated constructor operand
     */
    public function __construct(ErisGenerator $operands, ErisGenerator $operation)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Expression\Problem\IllegalCollationMix {
            return new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\IllegalCollationMix(operands: $parts[0], operation: $parts[1]);
        }, Generators::tuple(
            $operands,
            $operation
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Problem\IllegalCollationMix>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Expression\\Problem\\IllegalCollationMix' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new IllegalCollationMixGenerator(
            operands: Generators::tuple(
                Generators::tuple(
                    Generators::constant('latin1_bin'),
                    \Tests\Generator\MySql\Statement\Type\Resolved\CoercibilityGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility::Explicit])
                ),
                Generators::tuple(
                    Generators::constant('latin1_general_ci'),
                    \Tests\Generator\MySql\Statement\Type\Resolved\CoercibilityGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility::Explicit])
                )
            ),
            operation: Generators::constant('=')
        )];
    }

}
