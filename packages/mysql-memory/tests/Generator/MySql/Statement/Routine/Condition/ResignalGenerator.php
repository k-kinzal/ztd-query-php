<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Condition;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use InvalidArgumentException;
use LogicException;
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
     * Generates complete Resignal values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Condition\Resignal>|null $operands A constrained form of this concrete result
     * @param ErisGenerator<\SqlSemantics\Contract\AnalysisContext>|null $contexts Related declarations and session settings
     * @throws InvalidArgumentException When no form is available for the release.
     */
    public function __construct(\SqlSemantics\Contract\GrammarRelease $release, ?ErisGenerator $operands = null, ?ErisGenerator $contexts = null)
    {
        if ($operands === null) {
            $forms = [];
            if ($release === \SqlSemantics\Contract\GrammarRelease::MySql847) {
                foreach (self::forms() as $form) {
                    $forms[] = $form;
                }
            }

            if ($forms === []) {
                throw new InvalidArgumentException('No Resignal forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): \SqlSemantics\Platform\MySql\Statement\Routine\Condition\Resignal {
            if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Condition\Resignal) {
                throw new LogicException('Publishing Resignal must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionName|\SqlSemantics\Platform\MySql\Statement\Routine\Condition\SqlState|null> $condition Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Routine\Condition\SignalItem>>|null $items Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Condition\Resignal>
     */
    public static function operands(ErisGenerator $condition, ?ErisGenerator $items = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null): ErisGenerator
    {
        return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Condition\Resignal {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Condition\Resignal(condition: $parts[0], items: $parts[1], statementInformation: $parts[2], inputSource: $parts[3]);
        }, Generators::tuple($condition, $items ?? Generators::constant([]), $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null)));
    }
    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Condition\Resignal>>
     */
    public static function forms(): iterable
    {
        yield 'Resignal/67' => ResignalGenerator::operands(
            condition: Generators::constant(null),
            items: Generators::constant([])
        );
        yield 'Resignal/165' => ResignalGenerator::operands(
            condition: Generators::constant(null),
            items: Generators::tuple(
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
