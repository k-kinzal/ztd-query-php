<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Problem\Generation;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RefusedOperand values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\RefusedOperand>
 */
final class RefusedOperandGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\RefusedOperand> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $column Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\OperandKind> $kind Generated constructor operand
     * @param ErisGenerator<string|null>|null $function Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName|null>|null $scope Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand|null>|null $expression Generated constructor operand
     */
    public function __construct(ErisGenerator $column, ErisGenerator $kind, ?ErisGenerator $function = null, ?ErisGenerator $scope = null, ?ErisGenerator $expression = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\RefusedOperand {
            return new \SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\RefusedOperand(column: $parts[0], kind: $parts[1], function: $parts[2], scope: $parts[3], expression: $parts[4]);
        }, \Tests\Generator\Operands::tuple(
            $column,
            $kind,
            $function ?? Generators::constant(null),
            $scope ?? Generators::constant(null),
            $expression ?? Generators::constant(null)
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\RefusedOperand>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Table\\Problem\\Generation\\RefusedOperand' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new RefusedOperandGenerator(
            column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $suffix): string => 'g' . $suffix, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            ),
            kind: OperandKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\OperandKind::FunctionCall]),
            function: Generators::constant('user'),
            scope: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $suffix): string => 'p' . $suffix, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            expression: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                arguments: Generators::constant([]),
                parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
            )])
        )];
    }

}
