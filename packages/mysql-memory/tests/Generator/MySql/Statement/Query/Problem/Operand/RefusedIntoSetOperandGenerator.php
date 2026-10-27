<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query\Problem\Operand;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RefusedIntoSetOperand values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedIntoSetOperand>
 */
final class RefusedIntoSetOperandGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedIntoSetOperand> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Select> $operand Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator> $operator Generated constructor operand
     */
    public function __construct(ErisGenerator $operand, ErisGenerator $operator)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedIntoSetOperand {
            return new \SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedIntoSetOperand(operand: $parts[0], operator: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $operand,
            $operator
        ));
    }
    /**
     * Generates an input refusal with all of its retained operand dependencies.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedIntoSetOperand>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Query/Problem/Operand/RefusedIntoSetOperand' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new self(
            new \Tests\Generator\MySql\Statement\Query\SelectGenerator(
                Generators::constant([]),
                Generators::vector(1, new \Tests\Generator\MySql\Statement\Query\SelectExpressionGenerator(new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))))),
                intoPosition: \Tests\Generator\MySql\Statement\Query\Into\IntoPositionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Query\Into\IntoPosition::AfterItems]),
                into: new \Tests\Generator\MySql\Statement\Query\Into\IntoVariablesGenerator(Generators::vector(1, new \Tests\Generator\MySql\Statement\Variable\UserVariableGenerator(new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator()))),
            ),
            new \Tests\Generator\MySql\Statement\Query\Set\SetOperatorGenerator(),
        )];
    }

}
