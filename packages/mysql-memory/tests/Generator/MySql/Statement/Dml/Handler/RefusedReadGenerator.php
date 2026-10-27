<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Handler;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RefusedRead values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Handler\RefusedRead>
 */
final class RefusedReadGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Dml\Handler\RefusedRead> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Handler\OpenHandler> $handler Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Handler\ScanDirection|\SqlSemantics\Platform\MySql\Statement\Dml\Handler\IndexDirection|\SqlSemantics\Platform\MySql\Statement\Dml\Handler\KeyComparison> $mode Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null>|null $index Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Scalar|\SqlSemantics\Platform\MySql\Statement\Dml\DefaultRequest|\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand>>|null $values Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand|null>|null $where Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Limit|null>|null $limit Generated constructor operand
     */
    public function __construct(ErisGenerator $handler, ErisGenerator $mode, ?ErisGenerator $index = null, ?ErisGenerator $values = null, ?ErisGenerator $where = null, ?ErisGenerator $limit = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\Handler\RefusedRead {
            return new \SqlSemantics\Platform\MySql\Statement\Dml\Handler\RefusedRead(handler: $parts[0], mode: $parts[1], index: $parts[2], values: $parts[3], where: $parts[4], limit: $parts[5]);
        }, \Tests\Generator\Operands::tuple(
            $handler,
            $mode,
            $index ?? Generators::constant(null),
            $values ?? Generators::constant([]),
            $where ?? Generators::constant(null),
            $limit ?? Generators::constant(null)
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Handler\RefusedRead>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Dml\\Handler\\RefusedRead' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new RefusedReadGenerator(
            handler: new OpenHandlerGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $suffix): string => 'h' . $suffix, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                )
            ),
            mode: IndexDirectionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Dml\Handler\IndexDirection::Last]),
            index: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $suffix): string => 'i' . $suffix, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            ),
            values: Generators::constant([]),
            where: new \Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredOperandGenerator([new \Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredArithmeticGenerator(
                operator: \Tests\Generator\MySql\Statement\Expression\Operator\ArithmeticOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator::Plus]),
                left: new \Tests\Generator\MySql\Statement\Expression\RowOperandGenerator([new \Tests\Generator\MySql\Statement\Expression\RowGenerator(
                    elements: \Tests\Generator\Operands::tuple(
                        new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                        )]),
                        new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                            text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                        )])
                    ),
                    keyword: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                )]),
                right: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                    text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                )])
            )]),
            limit: new \Tests\Generator\MySql\Statement\Query\Clause\RowLimitGenerator(
                count: new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                    text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                ),
                offset: Generators::constant(null),
                spelling: Generators::constant(null)
            )
        )];
    }

}
