<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Expression\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes InvalidJsonCast values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Problem\InvalidJsonCast>
 */
final class InvalidJsonCastGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Expression\Problem\InvalidJsonCast> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind> $target Generated constructor operand
     */
    public function __construct(ErisGenerator $target)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Expression\Problem\InvalidJsonCast {
            return new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\InvalidJsonCast(target: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $target
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Problem\InvalidJsonCast>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Expression\\Problem\\InvalidJsonCast' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new InvalidJsonCastGenerator(
            target: \Tests\Generator\MySql\Statement\Type\Kind\CastKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::MultiPoint])
        )];
    }

}
