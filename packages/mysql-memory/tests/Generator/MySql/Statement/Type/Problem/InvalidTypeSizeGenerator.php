<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Type\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes InvalidTypeSize values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Problem\InvalidTypeSize>
 */
final class InvalidTypeSizeGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Type\Problem\InvalidTypeSize> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Problem\TypeLimit> $rule Generated constructor operand
     * @param ErisGenerator<string> $name Generated constructor operand
     * @param ErisGenerator<int>|null $actual Generated constructor operand
     * @param ErisGenerator<int>|null $maximum Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName|null>|null $scope Generated constructor operand
     */
    public function __construct(ErisGenerator $rule, ErisGenerator $name, ?ErisGenerator $actual = null, ?ErisGenerator $maximum = null, ?ErisGenerator $scope = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Type\Problem\InvalidTypeSize {
            return new \SqlSemantics\Platform\MySql\Statement\Type\Problem\InvalidTypeSize(rule: $parts[0], name: $parts[1], actual: $parts[2], maximum: $parts[3], scope: $parts[4]);
        }, \Tests\Generator\Operands::tuple(
            $rule,
            $name,
            $actual ?? Generators::constant(0),
            $maximum ?? Generators::constant(0),
            $scope ?? Generators::constant(null)
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Problem\InvalidTypeSize>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Type\\Problem\\InvalidTypeSize' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new InvalidTypeSizeGenerator(
            rule: TypeLimitGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Problem\TypeLimit::Empty]),
            name: Generators::constant(''),
            actual: Generators::constant(0),
            maximum: Generators::constant(0),
            scope: Generators::constant(null)
        )];
    }

}
