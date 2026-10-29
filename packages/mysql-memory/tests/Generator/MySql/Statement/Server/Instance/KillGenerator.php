<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Instance;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use InvalidArgumentException;
use LogicException;
use Tests\Generator\Composition;

/**
 * Composes Kill values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Instance\Kill>
 */
final class KillGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Instance\Kill> */
    use Composition;

    /**
     * Generates complete Kill values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Instance\Kill>|null $operands A constrained form of this concrete result
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
                throw new InvalidArgumentException('No Kill forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): \SqlSemantics\Platform\MySql\Statement\Server\Instance\Kill {
            if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Instance\Kill) {
                throw new LogicException('Publishing Kill must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Instance\KillScope|null> $scope Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand> $process Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Instance\Kill>
     */
    public static function operands(ErisGenerator $scope, ErisGenerator $process, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null): ErisGenerator
    {
        return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Instance\Kill {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Instance\Kill(scope: $parts[0], process: $parts[1], statementInformation: $parts[2], inputSource: $parts[3]);
        }, Generators::tuple($scope, $process, $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null)));
    }
    /**
     * Composes complete process requests whose generated row computations cannot be scalar.
     *
     * @return ErisGenerator<\SqlSemantics\Statement\AnalyzedStatement>
     */
    public static function refusedProcesses(\SqlSemantics\Contract\GrammarRelease $release): ErisGenerator
    {
        $numbers = new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 100)));
        $rows = Generators::bind(Generators::choose(2, 4), static fn (int $count): ErisGenerator => new \Tests\Generator\MySql\Statement\Expression\RowGenerator(Generators::vector($count, $numbers)));
        $processes = Generators::oneOf(...[
            $rows,
            Generators::map(static fn (\SqlSemantics\Platform\MySql\Statement\Expression\Row $row): \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredArithmetic => new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredArithmetic(\SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator::Plus, $row, new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1')), $rows),
            Generators::map(static fn (\SqlSemantics\Platform\MySql\Statement\Expression\Row $row): \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredTruthTest => new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredTruthTest($row, \SqlSemantics\Platform\MySql\Statement\Expression\Truth::True), $rows),
        ]);
        $requests = self::operands(Generators::elements([null, ...\SqlSemantics\Platform\MySql\Statement\Server\Instance\KillScope::cases()]), $processes);
        return new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $requests);
    }

    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Instance\Kill>>
     */
    public static function forms(): iterable
    {
        yield 'Kill/54' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                arguments: Generators::constant([]),
                                parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                            )])
                        )])
                    )])
                )])
            )])
        );
        yield 'Kill/223' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\TruthTestGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\ComparisonGenerator(
                        operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                        left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Predicate\MemberOfGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )]),
                                array: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Conversion\CastGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                                                function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                                                arguments: Generators::constant([]),
                                                                parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                                            )])
                                                        )])
                                                    )])
                                                )])
                                            )])
                                        )]),
                                        target: new \Tests\Generator\MySql\Statement\Type\CastTargetGenerator(
                                            kind: \Tests\Generator\MySql\Statement\Type\Kind\CastKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::Float]),
                                            length: Generators::constant(null),
                                            scale: Generators::constant(null),
                                            charset: Generators::constant(null),
                                            words: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                                        ),
                                        array: Generators::constant(false)
                                    )])
                                )]),
                                of: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )])
                        )])
                    )])
                )]),
                truth: \Tests\Generator\MySql\Statement\Expression\TruthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Truth::True]),
                negated: Generators::constant(false)
            )])
        );
        yield 'Kill/224' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\TruthTestGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\ComparisonGenerator(
                        operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                        left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Predicate\MemberOfGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )]),
                                array: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\ClockCallGenerator(
                                    clock: \Tests\Generator\MySql\Statement\Call\ClockGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\Clock::CurrentTime]),
                                    precision: Generators::constant(null),
                                    parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                                )]),
                                of: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )])
                        )])
                    )])
                )]),
                truth: \Tests\Generator\MySql\Statement\Expression\TruthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Truth::True]),
                negated: Generators::constant(false)
            )])
        );
        yield 'Kill/235' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\TruthTestGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\ComparisonGenerator(
                        operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                        left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Predicate\MemberOfGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )]),
                                array: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\Weight\WeightStringGenerator(
                                        subject: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                                            arguments: Generators::constant([]),
                                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                                        )])
                                                    )])
                                                )])
                                            )])
                                        )]),
                                        cast: \Tests\Generator\MySql\Statement\Call\Weight\WeightCastGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightCast::Char]),
                                        length: new \Tests\Generator\MySql\Statement\Literal\NumeralGenerator(
                                            text: Generators::constant('1'),
                                            hexadecimal: Generators::constant(false)
                                        ),
                                        levels: Generators::constant([]),
                                        range: Generators::constant(null)
                                    )])
                                )]),
                                of: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )])
                        )])
                    )])
                )]),
                truth: \Tests\Generator\MySql\Statement\Expression\TruthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Truth::True]),
                negated: Generators::constant(false)
            )])
        );
        yield 'Kill/299' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\LogicalGenerator(
                operator: \Tests\Generator\MySql\Statement\Expression\LogicalOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\LogicalOperator::Or]),
                left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                        function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                        arguments: Generators::constant([]),
                                        parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                    )])
                                )])
                            )])
                        )])
                    )])
                )]),
                right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                        function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                        arguments: Generators::constant([]),
                                        parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                    )])
                                )])
                            )])
                        )])
                    )])
                )]),
                symbolic: Generators::constant(false)
            )])
        );
        yield 'Kill/300' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\NotGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                        function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                        arguments: Generators::constant([]),
                                        parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                    )])
                                )])
                            )])
                        )])
                    )])
                )])
            )])
        );
        yield 'Kill/301' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\TruthTestGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\NullTestGenerator(
                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        negated: Generators::constant(false)
                    )])
                )]),
                truth: \Tests\Generator\MySql\Statement\Expression\TruthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Truth::True]),
                negated: Generators::constant(false)
            )])
        );
        yield 'Kill/302' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\TruthTestGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Subquery\QuantifiedComparisonGenerator(
                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                        quantifier: \Tests\Generator\MySql\Statement\Expression\Subquery\QuantifierGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Subquery\Quantifier::All]),
                        query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new \Tests\Generator\MySql\Statement\Query\ExplicitTableGenerator(
                            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                    quote: Generators::constant(null)
                                ),
                                schema: Generators::constant(null),
                                catalog: Generators::constant(null)
                            )
                        )])
                    )])
                )]),
                truth: \Tests\Generator\MySql\Statement\Expression\TruthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Truth::True]),
                negated: Generators::constant(false)
            )])
        );
        yield 'Kill/303' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\TruthTestGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\ComparisonGenerator(
                        operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                        left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Subquery\InQueryGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )]),
                                query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new \Tests\Generator\MySql\Statement\Query\ExplicitTableGenerator(
                                    table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                            quote: Generators::constant(null)
                                        ),
                                        schema: Generators::constant(null),
                                        catalog: Generators::constant(null)
                                    )
                                )]),
                                negated: Generators::constant(false)
                            )])
                        )])
                    )])
                )]),
                truth: \Tests\Generator\MySql\Statement\Expression\TruthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Truth::True]),
                negated: Generators::constant(false)
            )])
        );
        yield 'Kill/304' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\TruthTestGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\ComparisonGenerator(
                        operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                        left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Predicate\InListGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )]),
                                elements: Generators::tuple(
                                    \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                                            arguments: Generators::constant([]),
                                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                                        )])
                                                    )])
                                                )])
                                            )])
                                        )])
                                    )])
                                ),
                                negated: Generators::constant(false)
                            )])
                        )])
                    )])
                )]),
                truth: \Tests\Generator\MySql\Statement\Expression\TruthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Truth::True]),
                negated: Generators::constant(false)
            )])
        );
        yield 'Kill/305' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\TruthTestGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\ComparisonGenerator(
                        operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                        left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Predicate\BetweenGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )]),
                                low: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )]),
                                high: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                                function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                                arguments: Generators::constant([]),
                                                parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                            )])
                                        )])
                                    )])
                                )]),
                                negated: Generators::constant(false)
                            )])
                        )])
                    )])
                )]),
                truth: \Tests\Generator\MySql\Statement\Expression\TruthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Truth::True]),
                negated: Generators::constant(false)
            )])
        );
        yield 'Kill/306' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\TruthTestGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\ComparisonGenerator(
                        operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                        left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Predicate\SoundsLikeGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )]),
                                pattern: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )])
                    )])
                )]),
                truth: \Tests\Generator\MySql\Statement\Expression\TruthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Truth::True]),
                negated: Generators::constant(false)
            )])
        );
        yield 'Kill/307' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\TruthTestGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\ComparisonGenerator(
                        operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                        left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Predicate\LikeGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )]),
                                pattern: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                        function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                        arguments: Generators::constant([]),
                                        parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                    )])
                                )]),
                                escape: Generators::constant(null),
                                negated: Generators::constant(false)
                            )])
                        )])
                    )])
                )]),
                truth: \Tests\Generator\MySql\Statement\Expression\TruthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Truth::True]),
                negated: Generators::constant(false)
            )])
        );
        yield 'Kill/308' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\TruthTestGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\ComparisonGenerator(
                        operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                        left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Predicate\RegexpGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )]),
                                pattern: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )]),
                                negated: Generators::constant(false)
                            )])
                        )])
                    )])
                )]),
                truth: \Tests\Generator\MySql\Statement\Expression\TruthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Truth::True]),
                negated: Generators::constant(false)
            )])
        );
        yield 'Kill/309' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\TruthTestGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\ComparisonGenerator(
                        operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                        left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Subquery\InQueryGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Operator\ArithmeticGenerator(
                                        operator: \Tests\Generator\MySql\Statement\Expression\Operator\ArithmeticOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator::BitOr]),
                                        left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                                    function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                                    arguments: Generators::constant([]),
                                                    parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                                )])
                                            )])
                                        )]),
                                        right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                                    function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                                    arguments: Generators::constant([]),
                                                    parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                                )])
                                            )])
                                        )])
                                    )])
                                )]),
                                query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new \Tests\Generator\MySql\Statement\Query\ExplicitTableGenerator(
                                    table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                            quote: Generators::constant(null)
                                        ),
                                        schema: Generators::constant(null),
                                        catalog: Generators::constant(null)
                                    )
                                )]),
                                negated: Generators::constant(false)
                            )])
                        )])
                    )])
                )]),
                truth: \Tests\Generator\MySql\Statement\Expression\TruthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Truth::True]),
                negated: Generators::constant(false)
            )])
        );
        yield 'Kill/310' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\TruthTestGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\ComparisonGenerator(
                        operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                        left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Subquery\InQueryGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Operator\IntervalArithmeticGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                                    function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                                    arguments: Generators::constant([]),
                                                    parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                                )])
                                            )])
                                        )]),
                                        interval: new \Tests\Generator\MySql\Statement\Expression\Operator\IntervalGenerator(
                                            quantity: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                                                    function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                                                    arguments: Generators::constant([]),
                                                                    parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                                                )])
                                                            )])
                                                        )])
                                                    )])
                                                )])
                                            )]),
                                            unit: \Tests\Generator\MySql\Statement\Expression\IntervalUnitGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit::DayHour])
                                        ),
                                        subtract: Generators::constant(false)
                                    )])
                                )]),
                                query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new \Tests\Generator\MySql\Statement\Query\ExplicitTableGenerator(
                                    table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                            quote: Generators::constant(null)
                                        ),
                                        schema: Generators::constant(null),
                                        catalog: Generators::constant(null)
                                    )
                                )]),
                                negated: Generators::constant(false)
                            )])
                        )])
                    )])
                )]),
                truth: \Tests\Generator\MySql\Statement\Expression\TruthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Truth::True]),
                negated: Generators::constant(false)
            )])
        );
        yield 'Kill/311' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\TruthTestGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\ComparisonGenerator(
                        operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                        left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Predicate\MemberOfGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )]),
                                array: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Operator\UnaryGenerator(
                                        operator: \Tests\Generator\MySql\Statement\Expression\Operator\UnaryOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator::Not]),
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                                function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                                arguments: Generators::constant([]),
                                                parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                            )])
                                        )]),
                                        keyword: Generators::constant(false)
                                    )])
                                )]),
                                of: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )])
                        )])
                    )])
                )]),
                truth: \Tests\Generator\MySql\Statement\Expression\TruthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Truth::True]),
                negated: Generators::constant(false)
            )])
        );
        yield 'Kill/312' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\TruthTestGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\ComparisonGenerator(
                        operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                        left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Predicate\MemberOfGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )]),
                                array: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\FunctionCallGenerator(
                                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                            quote: Generators::constant(null)
                                        ),
                                        arguments: Generators::constant([]),
                                        schema: Generators::constant(null)
                                    )])
                                )]),
                                of: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )])
                        )])
                    )])
                )]),
                truth: \Tests\Generator\MySql\Statement\Expression\TruthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Truth::True]),
                negated: Generators::constant(false)
            )])
        );
        yield 'Kill/313' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\TruthTestGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\ComparisonGenerator(
                        operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                        left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Predicate\MemberOfGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )]),
                                array: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Operator\CollatedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                                function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                                arguments: Generators::constant([]),
                                                parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                            )])
                                        )]),
                                        collation: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                            quote: Generators::constant(null)
                                        )
                                    )])
                                )]),
                                of: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )])
                        )])
                    )])
                )]),
                truth: \Tests\Generator\MySql\Statement\Expression\TruthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Truth::True]),
                negated: Generators::constant(false)
            )])
        );
        yield 'Kill/314' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\TruthTestGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\ComparisonGenerator(
                        operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                        left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Predicate\MemberOfGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )]),
                                array: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Literal\ParameterGenerator(
                                    marker: Generators::constant('?'),
                                    position: Generators::constant(0)
                                )]),
                                of: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )])
                        )])
                    )])
                )]),
                truth: \Tests\Generator\MySql\Statement\Expression\TruthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Truth::True]),
                negated: Generators::constant(false)
            )])
        );
        yield 'Kill/315' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\TruthTestGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\ComparisonGenerator(
                        operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                        left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Predicate\MemberOfGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )]),
                                array: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Variable\VariableAssignmentGenerator(
                                        target: new \Tests\Generator\MySql\Statement\Variable\UserVariableGenerator(
                                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                                value: Generators::constant('localhost'),
                                                quote: Generators::constant(null)
                                            )
                                        ),
                                        value: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                                            arguments: Generators::constant([]),
                                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                                        )])
                                                    )])
                                                )])
                                            )])
                                        )])
                                    )])
                                )]),
                                of: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )])
                        )])
                    )])
                )]),
                truth: \Tests\Generator\MySql\Statement\Expression\TruthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Truth::True]),
                negated: Generators::constant(false)
            )])
        );
        yield 'Kill/316' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\TruthTestGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\ComparisonGenerator(
                        operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                        left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Predicate\MemberOfGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )]),
                                array: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\Aggregate\AggregateGenerator(
                                        function: \Tests\Generator\MySql\Statement\Call\Aggregate\AggregateFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction::Count]),
                                        arguments: Generators::constant([]),
                                        distinct: Generators::constant(false),
                                        all: Generators::constant(false),
                                        over: Generators::constant(null)
                                    )])
                                )]),
                                of: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )])
                        )])
                    )])
                )]),
                truth: \Tests\Generator\MySql\Statement\Expression\TruthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Truth::True]),
                negated: Generators::constant(false)
            )])
        );
        yield 'Kill/317' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\TruthTestGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\ComparisonGenerator(
                        operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                        left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Predicate\MemberOfGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )]),
                                array: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\Window\WindowFunctionGenerator(
                                        kind: \Tests\Generator\MySql\Statement\Call\Window\WindowFunctionKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind::RowNumber]),
                                        arguments: Generators::constant([]),
                                        over: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                            quote: Generators::constant(null)
                                        ),
                                        nulls: Generators::constant(null),
                                        edge: Generators::constant(null)
                                    )])
                                )]),
                                of: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )])
                        )])
                    )])
                )]),
                truth: \Tests\Generator\MySql\Statement\Expression\TruthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Truth::True]),
                negated: Generators::constant(false)
            )])
        );
        yield 'Kill/318' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\TruthTestGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\ComparisonGenerator(
                        operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                        left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Predicate\MemberOfGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )]),
                                array: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\FunctionCallGenerator(
                                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                            value: Generators::constant('CONCAT'),
                                            quote: Generators::constant(null)
                                        ),
                                        arguments: Generators::tuple(
                                            new \Tests\Generator\MySql\Statement\Call\CallArgumentGenerator(
                                                expression: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                    operand: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                                        function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                                        arguments: Generators::constant([]),
                                                        parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                                    )])
                                                )]),
                                                alias: Generators::constant(null),
                                                mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                                                layout: new \Tests\Generator\Semantics\Statement\Spelling\LayoutGenerator(
                                                    tokens: Generators::tuple(
                                                        new \Tests\Generator\Semantics\Statement\Spelling\SpelledGenerator(
                                                            gap: Generators::constant(''),
                                                            text: Generators::constant('(')
                                                        ),
                                                        new \Tests\Generator\Semantics\Statement\Spelling\SpelledGenerator(
                                                            gap: Generators::constant(' '),
                                                            text: Generators::constant('USER')
                                                        ),
                                                        new \Tests\Generator\Semantics\Statement\Spelling\SpelledGenerator(
                                                            gap: Generators::constant(' '),
                                                            text: Generators::constant('(')
                                                        ),
                                                        new \Tests\Generator\Semantics\Statement\Spelling\SpelledGenerator(
                                                            gap: Generators::constant(' '),
                                                            text: Generators::constant(')')
                                                        ),
                                                        new \Tests\Generator\Semantics\Statement\Spelling\SpelledGenerator(
                                                            gap: Generators::constant(' '),
                                                            text: Generators::constant(')')
                                                        )
                                                    ),
                                                    trail: Generators::constant('')
                                                ),
                                                spellingProfile: new \Tests\Generator\Semantics\Contract\LanguageProfileGenerator(
                                                    grammar: \Tests\Generator\Semantics\Contract\GrammarReleaseGenerator::selecting([\SqlSemantics\Contract\GrammarRelease::MySql847]),
                                                    lexical: new \Tests\Generator\Semantics\Contract\LexicalSettingsGenerator(
                                                        settings: Generators::constant('')
                                                    ),
                                                    parameters: \Tests\Generator\Semantics\Contract\ParameterStyleGenerator::selecting([\SqlSemantics\Contract\ParameterStyle::Native])
                                                )
                                            ),
                                            new \Tests\Generator\MySql\Statement\Call\CallArgumentGenerator(
                                                expression: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                    operand: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                                        function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                                        arguments: Generators::constant([]),
                                                        parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                                    )])
                                                )]),
                                                alias: Generators::constant(null),
                                                mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                                                layout: new \Tests\Generator\Semantics\Statement\Spelling\LayoutGenerator(
                                                    tokens: Generators::tuple(
                                                        new \Tests\Generator\Semantics\Statement\Spelling\SpelledGenerator(
                                                            gap: Generators::constant(''),
                                                            text: Generators::constant('(')
                                                        ),
                                                        new \Tests\Generator\Semantics\Statement\Spelling\SpelledGenerator(
                                                            gap: Generators::constant(' '),
                                                            text: Generators::constant('USER')
                                                        ),
                                                        new \Tests\Generator\Semantics\Statement\Spelling\SpelledGenerator(
                                                            gap: Generators::constant(' '),
                                                            text: Generators::constant('(')
                                                        ),
                                                        new \Tests\Generator\Semantics\Statement\Spelling\SpelledGenerator(
                                                            gap: Generators::constant(' '),
                                                            text: Generators::constant(')')
                                                        ),
                                                        new \Tests\Generator\Semantics\Statement\Spelling\SpelledGenerator(
                                                            gap: Generators::constant(' '),
                                                            text: Generators::constant(')')
                                                        )
                                                    ),
                                                    trail: Generators::constant('')
                                                ),
                                                spellingProfile: new \Tests\Generator\Semantics\Contract\LanguageProfileGenerator(
                                                    grammar: \Tests\Generator\Semantics\Contract\GrammarReleaseGenerator::selecting([\SqlSemantics\Contract\GrammarRelease::MySql847]),
                                                    lexical: new \Tests\Generator\Semantics\Contract\LexicalSettingsGenerator(
                                                        settings: Generators::constant('')
                                                    ),
                                                    parameters: \Tests\Generator\Semantics\Contract\ParameterStyleGenerator::selecting([\SqlSemantics\Contract\ParameterStyle::Native])
                                                )
                                            )
                                        ),
                                        schema: Generators::constant(null)
                                    )])
                                )]),
                                of: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )])
                        )])
                    )])
                )]),
                truth: \Tests\Generator\MySql\Statement\Expression\TruthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Truth::True]),
                negated: Generators::constant(false)
            )])
        );
        yield 'Kill/319' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\TruthTestGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\ComparisonGenerator(
                        operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                        left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Predicate\MemberOfGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )]),
                                array: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Subquery\ScalarSubqueryGenerator(
                                    query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new \Tests\Generator\MySql\Statement\Query\ParenthesizedQueryGenerator(
                                        query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new \Tests\Generator\MySql\Statement\Query\ExplicitTableGenerator(
                                            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                                                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                                    quote: Generators::constant(null)
                                                ),
                                                schema: Generators::constant(null),
                                                catalog: Generators::constant(null)
                                            )
                                        )])
                                    )])
                                )]),
                                of: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )])
                        )])
                    )])
                )]),
                truth: \Tests\Generator\MySql\Statement\Expression\TruthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Truth::True]),
                negated: Generators::constant(false)
            )])
        );
        yield 'Kill/320' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\TruthTestGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\ComparisonGenerator(
                        operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                        left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Predicate\MemberOfGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )]),
                                array: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Subquery\ExistsGenerator(
                                        query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new \Tests\Generator\MySql\Statement\Query\ExplicitTableGenerator(
                                            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                                                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                                    quote: Generators::constant(null)
                                                ),
                                                schema: Generators::constant(null),
                                                catalog: Generators::constant(null)
                                            )
                                        )])
                                    )])
                                )]),
                                of: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )])
                        )])
                    )])
                )]),
                truth: \Tests\Generator\MySql\Statement\Expression\TruthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Truth::True]),
                negated: Generators::constant(false)
            )])
        );
        yield 'Kill/321' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\TruthTestGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\ComparisonGenerator(
                        operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                        left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Predicate\MemberOfGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )]),
                                array: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Access\OdbcEscapeGenerator(
                                        kind: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                            quote: Generators::constant(null)
                                        ),
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                                                function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                                                arguments: Generators::constant([]),
                                                                parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                                            )])
                                                        )])
                                                    )])
                                                )])
                                            )])
                                        )])
                                    )])
                                )]),
                                of: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )])
                        )])
                    )])
                )]),
                truth: \Tests\Generator\MySql\Statement\Expression\TruthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Truth::True]),
                negated: Generators::constant(false)
            )])
        );
        yield 'Kill/322' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\TruthTestGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\ComparisonGenerator(
                        operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                        left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Predicate\MemberOfGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )]),
                                array: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Access\FullTextSearchGenerator(
                                        columns: Generators::tuple(
                                            new \Tests\Generator\MySql\Statement\Name\ColumnUseGenerator(
                                                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                                    quote: Generators::constant(null)
                                                ),
                                                qualifier: Generators::constant(null),
                                                dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                                            )
                                        ),
                                        against: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                                    function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                                    arguments: Generators::constant([]),
                                                    parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                                )])
                                            )])
                                        )]),
                                        mode: \Tests\Generator\MySql\Statement\Expression\Access\FullTextModeGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Access\FullTextMode::Boolean]),
                                        parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted]),
                                        stated: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                                    )])
                                )]),
                                of: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )])
                        )])
                    )])
                )]),
                truth: \Tests\Generator\MySql\Statement\Expression\TruthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Truth::True]),
                negated: Generators::constant(false)
            )])
        );
        yield 'Kill/323' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\TruthTestGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\ComparisonGenerator(
                        operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                        left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Predicate\MemberOfGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )]),
                                array: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Operator\BinaryCastGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                                function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                                arguments: Generators::constant([]),
                                                parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                            )])
                                        )])
                                    )])
                                )]),
                                of: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )])
                        )])
                    )])
                )]),
                truth: \Tests\Generator\MySql\Statement\Expression\TruthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Truth::True]),
                negated: Generators::constant(false)
            )])
        );
        yield 'Kill/324' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\TruthTestGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\ComparisonGenerator(
                        operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                        left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Predicate\MemberOfGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )]),
                                array: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Branching\CaseExpressionGenerator(
                                        operand: Generators::constant(null),
                                        branches: Generators::tuple(
                                            new \Tests\Generator\MySql\Statement\Expression\Branching\CaseBranchGenerator(
                                                condition: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                                                    function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                                                    arguments: Generators::constant([]),
                                                                    parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                                                )])
                                                            )])
                                                        )])
                                                    )])
                                                )]),
                                                result: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                                                    function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                                                    arguments: Generators::constant([]),
                                                                    parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                                                )])
                                                            )])
                                                        )])
                                                    )])
                                                )])
                                            )
                                        ),
                                        else: Generators::constant(null)
                                    )])
                                )]),
                                of: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )])
                        )])
                    )])
                )]),
                truth: \Tests\Generator\MySql\Statement\Expression\TruthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Truth::True]),
                negated: Generators::constant(false)
            )])
        );
        yield 'Kill/325' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\TruthTestGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\ComparisonGenerator(
                        operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                        left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Predicate\MemberOfGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )]),
                                array: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Conversion\CharsetConversionGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                                                function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                                                arguments: Generators::constant([]),
                                                                parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                                            )])
                                                        )])
                                                    )])
                                                )])
                                            )])
                                        )]),
                                        charset: new \Tests\Generator\MySql\Statement\Name\CharsetNameGenerator(
                                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                                value: Generators::constant('binary'),
                                                quote: Generators::constant(null)
                                            )
                                        )
                                    )])
                                )]),
                                of: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )])
                        )])
                    )])
                )]),
                truth: \Tests\Generator\MySql\Statement\Expression\TruthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Truth::True]),
                negated: Generators::constant(false)
            )])
        );
        yield 'Kill/326' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\TruthTestGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\ComparisonGenerator(
                        operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                        left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Predicate\MemberOfGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )]),
                                array: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Access\DefaultOfColumnGenerator(
                                        column: new \Tests\Generator\MySql\Statement\Name\ColumnUseGenerator(
                                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                                quote: Generators::constant(null)
                                            ),
                                            qualifier: Generators::constant(null),
                                            dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                                        )
                                    )])
                                )]),
                                of: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )])
                        )])
                    )])
                )]),
                truth: \Tests\Generator\MySql\Statement\Expression\TruthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Truth::True]),
                negated: Generators::constant(false)
            )])
        );
        yield 'Kill/327' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\TruthTestGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\ComparisonGenerator(
                        operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                        left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Predicate\MemberOfGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )]),
                                array: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Access\InsertedColumnGenerator(
                                        column: new \Tests\Generator\MySql\Statement\Name\ColumnUseGenerator(
                                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                                quote: Generators::constant(null)
                                            ),
                                            qualifier: Generators::constant(null),
                                            dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                                        )
                                    )])
                                )]),
                                of: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )])
                        )])
                    )])
                )]),
                truth: \Tests\Generator\MySql\Statement\Expression\TruthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Truth::True]),
                negated: Generators::constant(false)
            )])
        );
        yield 'Kill/328' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\TruthTestGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\ComparisonGenerator(
                        operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                        left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Predicate\MemberOfGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )]),
                                array: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Operator\IntervalAdditionGenerator(
                                        interval: new \Tests\Generator\MySql\Statement\Expression\Operator\IntervalGenerator(
                                            quantity: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                                                    function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                                                    arguments: Generators::constant([]),
                                                                    parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                                                )])
                                                            )])
                                                        )])
                                                    )])
                                                )])
                                            )]),
                                            unit: \Tests\Generator\MySql\Statement\Expression\IntervalUnitGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit::DayHour])
                                        ),
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                                                function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                                                arguments: Generators::constant([]),
                                                                parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                                            )])
                                                        )])
                                                    )])
                                                )])
                                            )])
                                        )])
                                    )])
                                )]),
                                of: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )])
                        )])
                    )])
                )]),
                truth: \Tests\Generator\MySql\Statement\Expression\TruthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Truth::True]),
                negated: Generators::constant(false)
            )])
        );
        yield 'Kill/329' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\TruthTestGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\ComparisonGenerator(
                        operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                        left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Predicate\MemberOfGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )]),
                                array: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Access\JsonExtractionGenerator(
                                        column: new \Tests\Generator\MySql\Statement\Name\ColumnUseGenerator(
                                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                                quote: Generators::constant(null)
                                            ),
                                            qualifier: Generators::constant(null),
                                            dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                                        ),
                                        path: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                                            value: Generators::constant('text'),
                                            radix: Generators::constant(null)
                                        ),
                                        unquote: Generators::constant(false)
                                    )])
                                )]),
                                of: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )])
                        )])
                    )])
                )]),
                truth: \Tests\Generator\MySql\Statement\Expression\TruthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Truth::True]),
                negated: Generators::constant(false)
            )])
        );
        yield 'Kill/330' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\TruthTestGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\ComparisonGenerator(
                        operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                        left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Predicate\MemberOfGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )]),
                                array: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\CharCallGenerator(
                                        arguments: Generators::tuple(
                                            \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                                                function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                                                arguments: Generators::constant([]),
                                                                parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                                            )])
                                                        )])
                                                    )])
                                                )])
                                            )])
                                        ),
                                        charset: Generators::constant(null)
                                    )])
                                )]),
                                of: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )])
                        )])
                    )])
                )]),
                truth: \Tests\Generator\MySql\Statement\Expression\TruthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Truth::True]),
                negated: Generators::constant(false)
            )])
        );
        yield 'Kill/331' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\TruthTestGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\ComparisonGenerator(
                        operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                        left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Predicate\MemberOfGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )]),
                                array: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\Json\JsonValueCallGenerator(
                                        document: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )]),
                                        path: \Tests\Generator\MySql\Statement\Literal\StringLiteralGenerator::fromOperands(
                                            segments: Generators::tuple(
                                                Generators::constant('text')
                                            ),
                                            introducer: Generators::constant(null),
                                            national: Generators::constant(false),
                                            collation: Generators::constant(null)
                                        ),
                                        returning: Generators::constant(null),
                                        onEmpty: Generators::constant(null),
                                        onError: Generators::constant(null)
                                    )])
                                )]),
                                of: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )])
                        )])
                    )])
                )]),
                truth: \Tests\Generator\MySql\Statement\Expression\TruthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Truth::True]),
                negated: Generators::constant(false)
            )])
        );
        yield 'Kill/332' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\TruthTestGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\ComparisonGenerator(
                        operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                        left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Predicate\MemberOfGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )]),
                                array: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\TrimGenerator(
                                        subject: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                                            arguments: Generators::constant([]),
                                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                                        )])
                                                    )])
                                                )])
                                            )])
                                        )]),
                                        side: Generators::constant(null),
                                        removed: Generators::constant(null)
                                    )])
                                )]),
                                of: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )])
                        )])
                    )])
                )]),
                truth: \Tests\Generator\MySql\Statement\Expression\TruthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Truth::True]),
                negated: Generators::constant(false)
            )])
        );
        yield 'Kill/333' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\TruthTestGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\ComparisonGenerator(
                        operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                        left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Predicate\MemberOfGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )]),
                                array: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\TrimGenerator(
                                        subject: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                                            arguments: Generators::constant([]),
                                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                                        )])
                                                    )])
                                                )])
                                            )])
                                        )]),
                                        side: \Tests\Generator\MySql\Statement\Call\TrimSideGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\TrimSide::Leading]),
                                        removed: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                                            arguments: Generators::constant([]),
                                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                                        )])
                                                    )])
                                                )])
                                            )])
                                        )])
                                    )])
                                )]),
                                of: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )])
                        )])
                    )])
                )]),
                truth: \Tests\Generator\MySql\Statement\Expression\TruthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Truth::True]),
                negated: Generators::constant(false)
            )])
        );
        yield 'Kill/334' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\TruthTestGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\ComparisonGenerator(
                        operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                        left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Predicate\MemberOfGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )]),
                                array: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([\Tests\Generator\MySql\Statement\Call\Temporal\DateArithmeticGenerator::fromOperands(
                                        subtract: Generators::constant(false),
                                        date: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                                            arguments: Generators::constant([]),
                                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                                        )])
                                                    )])
                                                )])
                                            )])
                                        )]),
                                        quantity: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                                            arguments: Generators::constant([]),
                                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                                        )])
                                                    )])
                                                )])
                                            )])
                                        )]),
                                        unit: \Tests\Generator\MySql\Statement\Expression\IntervalUnitGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit::DayHour])
                                    )])
                                )]),
                                of: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )])
                        )])
                    )])
                )]),
                truth: \Tests\Generator\MySql\Statement\Expression\TruthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Truth::True]),
                negated: Generators::constant(false)
            )])
        );
        yield 'Kill/335' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\TruthTestGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\ComparisonGenerator(
                        operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                        left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Predicate\MemberOfGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )]),
                                array: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\ExtractGenerator(
                                        unit: \Tests\Generator\MySql\Statement\Expression\IntervalUnitGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit::DayHour]),
                                        source: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                                            arguments: Generators::constant([]),
                                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                                        )])
                                                    )])
                                                )])
                                            )])
                                        )])
                                    )])
                                )]),
                                of: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )])
                        )])
                    )])
                )]),
                truth: \Tests\Generator\MySql\Statement\Expression\TruthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Truth::True]),
                negated: Generators::constant(false)
            )])
        );
        yield 'Kill/336' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\TruthTestGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\ComparisonGenerator(
                        operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                        left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Predicate\MemberOfGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )]),
                                array: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\Temporal\GetFormatGenerator(
                                        format: \Tests\Generator\MySql\Statement\Call\Temporal\TemporalFormatGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\Temporal\TemporalFormat::Date]),
                                        standard: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                                            arguments: Generators::constant([]),
                                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                                        )])
                                                    )])
                                                )])
                                            )])
                                        )])
                                    )])
                                )]),
                                of: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )])
                        )])
                    )])
                )]),
                truth: \Tests\Generator\MySql\Statement\Expression\TruthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Truth::True]),
                negated: Generators::constant(false)
            )])
        );
        yield 'Kill/337' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\TruthTestGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\ComparisonGenerator(
                        operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                        left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Predicate\MemberOfGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )]),
                                array: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\PositionGenerator(
                                        substring: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                                function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                                arguments: Generators::constant([]),
                                                parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                            )])
                                        )]),
                                        string: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                                            arguments: Generators::constant([]),
                                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                                        )])
                                                    )])
                                                )])
                                            )])
                                        )])
                                    )])
                                )]),
                                of: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )])
                        )])
                    )])
                )]),
                truth: \Tests\Generator\MySql\Statement\Expression\TruthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Truth::True]),
                negated: Generators::constant(false)
            )])
        );
        yield 'Kill/338' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\TruthTestGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\ComparisonGenerator(
                        operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                        left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Predicate\MemberOfGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )]),
                                array: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\Temporal\TimestampCallGenerator(
                                        operation: \Tests\Generator\MySql\Statement\Call\Temporal\TimestampOperationGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\Temporal\TimestampOperation::Add]),
                                        unit: \Tests\Generator\MySql\Statement\Expression\IntervalUnitGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit::Day]),
                                        first: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                                            arguments: Generators::constant([]),
                                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                                        )])
                                                    )])
                                                )])
                                            )])
                                        )]),
                                        second: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                                            arguments: Generators::constant([]),
                                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                                        )])
                                                    )])
                                                )])
                                            )])
                                        )])
                                    )])
                                )]),
                                of: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )])
                        )])
                    )])
                )]),
                truth: \Tests\Generator\MySql\Statement\Expression\TruthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Truth::True]),
                negated: Generators::constant(false)
            )])
        );
        yield 'Kill/339' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\TruthTestGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\ComparisonGenerator(
                        operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                        left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Predicate\MemberOfGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )]),
                                array: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\Weight\WeightStringParametersGenerator(
                                        subject: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                                            arguments: Generators::constant([]),
                                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                                        )])
                                                    )])
                                                )])
                                            )])
                                        )]),
                                        length: new \Tests\Generator\MySql\Statement\Literal\NumeralGenerator(
                                            text: Generators::constant('1'),
                                            hexadecimal: Generators::constant(false)
                                        ),
                                        weights: new \Tests\Generator\MySql\Statement\Literal\NumeralGenerator(
                                            text: Generators::constant('1'),
                                            hexadecimal: Generators::constant(false)
                                        ),
                                        flags: new \Tests\Generator\MySql\Statement\Literal\NumeralGenerator(
                                            text: Generators::constant('1'),
                                            hexadecimal: Generators::constant(false)
                                        )
                                    )])
                                )]),
                                of: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )])
                        )])
                    )])
                )]),
                truth: \Tests\Generator\MySql\Statement\Expression\TruthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Truth::True]),
                negated: Generators::constant(false)
            )])
        );
        yield 'Kill/340' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\TruthTestGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\ComparisonGenerator(
                        operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                        left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Predicate\MemberOfGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )]),
                                array: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\Aggregate\JsonObjectAggregateGenerator(
                                        key: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                                            arguments: Generators::constant([]),
                                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                                        )])
                                                    )])
                                                )])
                                            )])
                                        )]),
                                        value: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                                            arguments: Generators::constant([]),
                                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                                        )])
                                                    )])
                                                )])
                                            )])
                                        )]),
                                        keyAll: Generators::constant(false),
                                        valueAll: Generators::constant(false),
                                        over: Generators::constant(null)
                                    )])
                                )]),
                                of: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )])
                        )])
                    )])
                )]),
                truth: \Tests\Generator\MySql\Statement\Expression\TruthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Truth::True]),
                negated: Generators::constant(false)
            )])
        );
        yield 'Kill/341' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\TruthTestGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\ComparisonGenerator(
                        operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                        left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Predicate\MemberOfGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )]),
                                array: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\Aggregate\GroupConcatGenerator(
                                        arguments: Generators::tuple(
                                            \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                                                function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                                                arguments: Generators::constant([]),
                                                                parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                                            )])
                                                        )])
                                                    )])
                                                )])
                                            )])
                                        ),
                                        distinct: Generators::constant(false),
                                        order: Generators::constant([]),
                                        separator: Generators::constant(null),
                                        over: Generators::constant(null)
                                    )])
                                )]),
                                of: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )])
                        )])
                    )])
                )]),
                truth: \Tests\Generator\MySql\Statement\Expression\TruthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Truth::True]),
                negated: Generators::constant(false)
            )])
        );
        yield 'Kill/342' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\TruthTestGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\ComparisonGenerator(
                        operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                        left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Predicate\MemberOfGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )]),
                                array: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\Window\WindowFunctionGenerator(
                                        kind: \Tests\Generator\MySql\Statement\Call\Window\WindowFunctionKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind::Tile]),
                                        arguments: Generators::tuple(
                                            new \Tests\Generator\MySql\Statement\Call\Window\RoutineVariableGenerator(
                                                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                                    quote: Generators::constant(null)
                                                )
                                            )
                                        ),
                                        over: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                            quote: Generators::constant(null)
                                        ),
                                        nulls: Generators::constant(null),
                                        edge: Generators::constant(null)
                                    )])
                                )]),
                                of: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )])
                        )])
                    )])
                )]),
                truth: \Tests\Generator\MySql\Statement\Expression\TruthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Truth::True]),
                negated: Generators::constant(false)
            )])
        );
        yield 'Kill/343' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\TruthTestGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\ComparisonGenerator(
                        operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                        left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Predicate\MemberOfGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )]),
                                array: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\Window\WindowFunctionGenerator(
                                        kind: \Tests\Generator\MySql\Statement\Call\Window\WindowFunctionKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind::Lead]),
                                        arguments: Generators::tuple(
                                            \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                                                function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                                                arguments: Generators::constant([]),
                                                                parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                                            )])
                                                        )])
                                                    )])
                                                )])
                                            )])
                                        ),
                                        over: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                            quote: Generators::constant(null)
                                        ),
                                        nulls: \Tests\Generator\MySql\Statement\Call\Window\NullTreatmentGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\Window\NullTreatment::Respect]),
                                        edge: Generators::constant(null)
                                    )])
                                )]),
                                of: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )])
                        )])
                    )])
                )]),
                truth: \Tests\Generator\MySql\Statement\Expression\TruthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Truth::True]),
                negated: Generators::constant(false)
            )])
        );
        yield 'Kill/344' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\TruthTestGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\ComparisonGenerator(
                        operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                        left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Predicate\MemberOfGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )]),
                                array: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\Window\WindowFunctionGenerator(
                                        kind: \Tests\Generator\MySql\Statement\Call\Window\WindowFunctionKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind::NthValue]),
                                        arguments: Generators::tuple(
                                            \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                                                function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                                                arguments: Generators::constant([]),
                                                                parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                                            )])
                                                        )])
                                                    )])
                                                )])
                                            )]),
                                            \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                                function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                                arguments: Generators::constant([]),
                                                parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                            )])
                                        ),
                                        over: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                                            quote: Generators::constant(null)
                                        ),
                                        nulls: Generators::constant(null),
                                        edge: \Tests\Generator\MySql\Statement\Call\Window\CountingEdgeGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\Window\CountingEdge::First])
                                    )])
                                )]),
                                of: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )])
                        )])
                    )])
                )]),
                truth: \Tests\Generator\MySql\Statement\Expression\TruthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Truth::True]),
                negated: Generators::constant(false)
            )])
        );
        yield 'Kill/345' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\TruthTestGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\ComparisonGenerator(
                        operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                        left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Predicate\MemberOfGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )]),
                                array: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\Window\WindowFunctionGenerator(
                                        kind: \Tests\Generator\MySql\Statement\Call\Window\WindowFunctionKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind::RowNumber]),
                                        arguments: Generators::constant([]),
                                        over: new \Tests\Generator\MySql\Statement\Call\WindowSpecificationGenerator([new \Tests\Generator\MySql\Statement\Call\Window\WindowSpecGenerator(
                                            base: Generators::constant(null),
                                            partition: Generators::constant([]),
                                            order: Generators::constant([]),
                                            frame: Generators::constant(null)
                                        )]),
                                        nulls: Generators::constant(null),
                                        edge: Generators::constant(null)
                                    )])
                                )]),
                                of: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )])
                        )])
                    )])
                )]),
                truth: \Tests\Generator\MySql\Statement\Expression\TruthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Truth::True]),
                negated: Generators::constant(false)
            )])
        );
        yield 'Kill/346' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\TruthTestGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\ComparisonGenerator(
                        operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                        left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Predicate\MemberOfGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )]),
                                array: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\Window\WindowFunctionGenerator(
                                        kind: \Tests\Generator\MySql\Statement\Call\Window\WindowFunctionKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind::RowNumber]),
                                        arguments: Generators::constant([]),
                                        over: new \Tests\Generator\MySql\Statement\Call\WindowSpecificationGenerator([new \Tests\Generator\MySql\Statement\Call\Window\WindowSpecGenerator(
                                            base: Generators::constant(null),
                                            partition: Generators::constant([]),
                                            order: Generators::constant([]),
                                            frame: new \Tests\Generator\MySql\Statement\Call\Window\FrameGenerator(
                                                unit: \Tests\Generator\MySql\Statement\Call\Window\FrameUnitGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\Window\FrameUnit::Rows]),
                                                start: new \Tests\Generator\MySql\Statement\Call\Window\FrameBoundGenerator(
                                                    kind: \Tests\Generator\MySql\Statement\Call\Window\FrameBoundKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind::UnboundedPreceding]),
                                                    offset: Generators::constant(null),
                                                    unit: Generators::constant(null)
                                                ),
                                                end: Generators::constant(null),
                                                exclusion: Generators::constant(null)
                                            )
                                        )]),
                                        nulls: Generators::constant(null),
                                        edge: Generators::constant(null)
                                    )])
                                )]),
                                of: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )])
                        )])
                    )])
                )]),
                truth: \Tests\Generator\MySql\Statement\Expression\TruthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Truth::True]),
                negated: Generators::constant(false)
            )])
        );
        yield 'Kill/348' => KillGenerator::operands(
            scope: Generators::constant(null),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\TruthTestGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\ComparisonGenerator(
                        operator: \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
                        left: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )])
                            )])
                        )]),
                        right: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\Predicate\MemberOfGenerator(
                                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )])
                                    )])
                                )]),
                                array: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\Json\JsonValueCallGenerator(
                                        document: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                            function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                            arguments: Generators::constant([]),
                                            parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                                        )]),
                                        path: \Tests\Generator\MySql\Statement\Literal\StringLiteralGenerator::fromOperands(
                                            segments: Generators::tuple(
                                                Generators::constant('text')
                                            ),
                                            introducer: Generators::constant(null),
                                            national: Generators::constant(false),
                                            collation: Generators::constant(null)
                                        ),
                                        returning: Generators::constant(null),
                                        onEmpty: new \Tests\Generator\MySql\Statement\Call\Json\JsonResponseGenerator(
                                            kind: \Tests\Generator\MySql\Statement\Call\Json\JsonResponseKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\Json\JsonResponseKind::Error]),
                                            default: Generators::constant(null)
                                        ),
                                        onError: Generators::constant(null)
                                    )])
                                )]),
                                of: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                            )])
                        )])
                    )])
                )]),
                truth: \Tests\Generator\MySql\Statement\Expression\TruthGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Truth::True]),
                negated: Generators::constant(false)
            )])
        );
        yield 'Kill/375' => KillGenerator::operands(
            scope: KillScopeGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Server\Instance\KillScope::Connection]),
            process: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                        operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: \Tests\Generator\Semantics\Statement\ScalarGenerator::composed([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                arguments: Generators::constant([]),
                                parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                            )])
                        )])
                    )])
                )])
            )])
        );
    }

}
