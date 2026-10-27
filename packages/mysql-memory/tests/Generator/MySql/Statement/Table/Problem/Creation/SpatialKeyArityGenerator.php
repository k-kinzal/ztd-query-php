<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Problem\Creation;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes SpatialKeyArity values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\SpatialKeyArity>
 */
final class SpatialKeyArityGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\SpatialKeyArity> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Key\KeyPart> $owner Generated constructor operand
     */
    public function __construct(ErisGenerator $owner)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\SpatialKeyArity {
            return new \SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\SpatialKeyArity(owner: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $owner
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\SpatialKeyArity>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Table\\Problem\\Creation\\SpatialKeyArity' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new SpatialKeyArityGenerator(
            owner: new \Tests\Generator\MySql\Statement\Table\Key\ColumnPartGenerator(
                column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('a'),
                    quote: Generators::constant(null)
                ),
                length: Generators::constant(null),
                direction: Generators::constant(null)
            )
        )];
    }

}
