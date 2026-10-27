<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Condition;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Resignal values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Condition\Resignal>
 */
final class ResignalGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Condition\Resignal> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionName|\SqlSemantics\Platform\MySql\Statement\Routine\Condition\SqlState|null> $condition Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Routine\Condition\SignalItem>>|null $items Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $condition, ?ErisGenerator $items = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Condition\Resignal {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Condition\Resignal(condition: $parts[0], items: $parts[1], statementInformation: $parts[2], inputSource: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $condition,
            $items ?? Generators::constant([]),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Condition\Resignal>>
     */
    public static function forms(): iterable
    {
        yield 'Resignal/67' => new ResignalGenerator(
            condition: Generators::constant(null),
            items: Generators::constant([])
        );
        yield 'Resignal/165' => new ResignalGenerator(
            condition: Generators::constant(null),
            items: \Tests\Generator\Operands::tuple(
                new SignalItemGenerator(
                    name: ConditionItemNameGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionItemName::ClassOrigin]),
                    value: new \Tests\Generator\MySql\Statement\LiteralExpressionGenerator([new \Tests\Generator\MySql\Statement\Literal\BooleanLiteralGenerator(
                        value: Generators::constant(false)
                    )])
                )
            )
        );
    }

}
