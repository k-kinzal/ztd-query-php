<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Event\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RefusedOperand values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Event\Problem\RefusedOperand>
 */
final class RefusedOperandGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Event\Problem\RefusedOperand> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName> $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Event\Problem\OperandWidth|\SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet|\SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse> $problem Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\Account|null>|null $definer Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Operand>>|null $operands Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ErisGenerator $problem, ?ErisGenerator $definer = null, ?ErisGenerator $operands = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Event\Problem\RefusedOperand {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Event\Problem\RefusedOperand(name: $parts[0], problem: $parts[1], definer: $parts[2], operands: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $name,
            $problem,
            $definer ?? Generators::constant(null),
            $operands ?? Generators::constant([])
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Event\Problem\RefusedOperand>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Routine\\Event\\Problem\\RefusedOperand' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new RefusedOperandGenerator(
            name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $suffix): string => 'e' . $suffix, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            problem: new OperandWidthGenerator(
                problem: new \Tests\Generator\MySql\Statement\Expression\Problem\OperandColumnsGenerator(
                    expected: Generators::constant(1),
                    actual: Generators::constant(2),
                    expression: Generators::constant(null)
                ),
                clause: \Tests\Generator\MySql\Statement\Routine\Event\ClauseGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Routine\Event\Clause::Starts]),
                rowValue: Generators::constant(false)
            ),
            definer: Generators::constant(null),
            operands: \Tests\Generator\Operands::tuple(
                new \Tests\Generator\Semantics\Statement\OperandGenerator([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                    text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                )]),
                new \Tests\Generator\Semantics\Statement\OperandGenerator([new \Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredCallGenerator(
                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::constant('ABS'),
                        quote: Generators::constant(null)
                    ),
                    arguments: \Tests\Generator\Operands::tuple(
                        new \Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredCallArgumentGenerator(
                            expression: new \Tests\Generator\MySql\Statement\Expression\RowOperandGenerator([new \Tests\Generator\MySql\Statement\Expression\RowGenerator(
                                elements: \Tests\Generator\Operands::tuple(
                                    new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                        text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                    )]),
                                    new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                        text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                    )])
                                ),
                                keyword: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )]),
                            alias: Generators::constant(null),
                            mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As])
                        )
                    ),
                    schema: Generators::constant(null)
                )])
            )
        )];
    }

}
