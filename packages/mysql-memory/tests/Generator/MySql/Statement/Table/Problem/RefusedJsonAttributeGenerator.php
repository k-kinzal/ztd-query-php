<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RefusedJsonAttribute values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Problem\RefusedJsonAttribute>
 */
final class RefusedJsonAttributeGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Table\Problem\RefusedJsonAttribute> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text> $attribute Generated constructor operand
     * @param ErisGenerator<string> $reason Generated constructor operand
     * @param ErisGenerator<int> $position Generated constructor operand
     * @param ErisGenerator<bool>|null $deep Generated constructor operand
     */
    public function __construct(ErisGenerator $attribute, ErisGenerator $reason, ErisGenerator $position, ?ErisGenerator $deep = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Table\Problem\RefusedJsonAttribute {
            return new \SqlSemantics\Platform\MySql\Statement\Table\Problem\RefusedJsonAttribute(attribute: $parts[0], reason: $parts[1], position: $parts[2], deep: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $attribute,
            $reason,
            $position,
            $deep ?? Generators::constant(false)
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Problem\RefusedJsonAttribute>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Table\\Problem\\RefusedJsonAttribute' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new RefusedJsonAttributeGenerator(
            attribute: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                value: Generators::constant('bad'),
                radix: Generators::constant(null)
            ),
            reason: Generators::constant('Invalid value.'),
            position: Generators::constant(0),
            deep: Generators::constant(false)
        )];
    }

}
