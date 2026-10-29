<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes InvalidColumnAttribute values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Problem\InvalidColumnAttribute>
 */
final class InvalidColumnAttributeGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Table\Problem\InvalidColumnAttribute> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $column Generated constructor operand
     * @param ErisGenerator<string> $attribute Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName|null>|null $scope Generated constructor operand
     */
    public function __construct(ErisGenerator $column, ErisGenerator $attribute, ?ErisGenerator $scope = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Table\Problem\InvalidColumnAttribute {
            $attribute = $parts[1];
            \SqlSemantics\Diagnostic\Check::input(in_array($attribute, ['SRID', 'DEFAULT', 'ON UPDATE', 'AUTO_INCREMENT'], true), 'A generated incompatible column attribute names its SQL clause.');
            return new \SqlSemantics\Platform\MySql\Statement\Table\Problem\InvalidColumnAttribute(column: $parts[0], attribute: $attribute, scope: $parts[2]);
        }, Generators::tuple(
            $column,
            $attribute,
            $scope ?? Generators::constant(null)
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Problem\InvalidColumnAttribute>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Table\\Problem\\InvalidColumnAttribute' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new InvalidColumnAttributeGenerator(
            column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $suffix): string => 'c' . $suffix, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            ),
            attribute: Generators::constant('AUTO_INCREMENT'),
            scope: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $suffix): string => 't' . $suffix, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $suffix): string => 'd' . $suffix, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                catalog: Generators::constant(null)
            )
        )];
    }

}
