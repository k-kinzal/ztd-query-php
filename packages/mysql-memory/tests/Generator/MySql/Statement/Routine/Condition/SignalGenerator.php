<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Condition;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Signal values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Condition\Signal>
 */
final class SignalGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Condition\Signal> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionName|\SqlSemantics\Platform\MySql\Statement\Routine\Condition\SqlState> $condition Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Routine\Condition\SignalItem>>|null $items Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $condition, ?ErisGenerator $items = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Condition\Signal {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Condition\Signal(condition: $parts[0], items: $parts[1], statementInformation: $parts[2], inputSource: $parts[3]);
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
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Condition\Signal>>
     */
    public static function forms(): iterable
    {
        yield 'Signal/121' => new SignalGenerator(
            condition: new ConditionNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                )
            ),
            items: Generators::constant([])
        );
    }

    /**
     * Composes further SQL roles and version-specific forms from typed operands.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Condition\Signal>}>
     */
    public static function additionalForms(): iterable
    {
        yield 'Signal/additional/19' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new SignalGenerator(
            condition: new SqlStateGenerator(
                state: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                    value: Generators::constant('42S02'),
                    radix: Generators::constant(null)
                )
            ),
            items: Generators::constant([])
        )];
    }

}
