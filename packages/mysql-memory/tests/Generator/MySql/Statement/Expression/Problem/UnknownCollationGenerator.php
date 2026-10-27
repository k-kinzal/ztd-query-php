<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Expression\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes UnknownCollation values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation>
 */
final class UnknownCollationGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation> */
    use Composition;

    /**
     * @param ErisGenerator<string> $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Operand|null>|null $operand Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ?ErisGenerator $operand = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation {
            return new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation(name: $parts[0], operand: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $name,
            $operand ?? Generators::constant(null)
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Expression\\Problem\\UnknownCollation' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new UnknownCollationGenerator(
            name: Generators::constant('missing'),
            operand: Generators::constant(null)
        )];
    }

}
