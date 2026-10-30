<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Statement\Operand;
use Tests\Generator\MySql\Statement\Call\Aggregate\AggregateGenerator;
use Tests\Generator\MySql\Statement\Call\Aggregate\GroupConcatGenerator;
use Tests\Generator\MySql\Statement\Call\Aggregate\JsonObjectAggregateGenerator;
use Tests\Generator\MySql\Statement\Call\CharCallGenerator;
use Tests\Generator\MySql\Statement\Call\ClockCallGenerator;
use Tests\Generator\MySql\Statement\Call\ExtractGenerator;
use Tests\Generator\MySql\Statement\Call\FunctionCallGenerator;
use Tests\Generator\MySql\Statement\Call\Json\JsonValueCallGenerator;
use Tests\Generator\MySql\Statement\Call\KeywordCallGenerator;
use Tests\Generator\MySql\Statement\Call\PositionGenerator;
use Tests\Generator\MySql\Statement\Call\Temporal\DateArithmeticGenerator;
use Tests\Generator\MySql\Statement\Call\Temporal\GetFormatGenerator;
use Tests\Generator\MySql\Statement\Call\Temporal\TimestampCallGenerator;
use Tests\Generator\MySql\Statement\Call\TrimGenerator;
use Tests\Generator\MySql\Statement\Call\Weight\WeightStringGenerator;
use Tests\Generator\MySql\Statement\Call\Weight\WeightStringParametersGenerator;
use Tests\Generator\MySql\Statement\Call\Window\RoutineVariableGenerator;
use Tests\Generator\MySql\Statement\Call\Window\WindowFunctionGenerator;
use Tests\Generator\MySql\Statement\Dml\DefaultRequestGenerator;
use Tests\Generator\MySql\Statement\Expression\Access\DefaultOfColumnGenerator;
use Tests\Generator\MySql\Statement\Expression\Access\FullTextSearchGenerator;
use Tests\Generator\MySql\Statement\Expression\Access\InsertedColumnGenerator;
use Tests\Generator\MySql\Statement\Expression\Access\JsonExtractionGenerator;
use Tests\Generator\MySql\Statement\Expression\Access\OdbcEscapeGenerator;
use Tests\Generator\MySql\Statement\Expression\Branching\CaseExpressionGenerator;
use Tests\Generator\MySql\Statement\Expression\ComparisonGenerator;
use Tests\Generator\MySql\Statement\Expression\Conversion\AtTimeZoneGenerator;
use Tests\Generator\MySql\Statement\Expression\Conversion\CastGenerator;
use Tests\Generator\MySql\Statement\Expression\Conversion\CharsetConversionGenerator;
use Tests\Generator\MySql\Statement\Expression\GroupedGenerator;
use Tests\Generator\MySql\Statement\Expression\GroupedRowGenerator;
use Tests\Generator\MySql\Statement\Expression\LogicalGenerator;
use Tests\Generator\MySql\Statement\Expression\NotGenerator;
use Tests\Generator\MySql\Statement\Expression\NullTestGenerator;
use Tests\Generator\MySql\Statement\Expression\Operator\ArithmeticGenerator;
use Tests\Generator\MySql\Statement\Expression\Operator\BinaryCastGenerator;
use Tests\Generator\MySql\Statement\Expression\Operator\CollatedGenerator;
use Tests\Generator\MySql\Statement\Expression\Operator\ConcatenationGenerator;
use Tests\Generator\MySql\Statement\Expression\Operator\IntervalAdditionGenerator;
use Tests\Generator\MySql\Statement\Expression\Operator\IntervalArithmeticGenerator;
use Tests\Generator\MySql\Statement\Expression\Operator\UnaryGenerator;
use Tests\Generator\MySql\Statement\Expression\Predicate\BetweenGenerator;
use Tests\Generator\MySql\Statement\Expression\Predicate\InListGenerator;
use Tests\Generator\MySql\Statement\Expression\Predicate\LikeGenerator;
use Tests\Generator\MySql\Statement\Expression\Predicate\MemberOfGenerator;
use Tests\Generator\MySql\Statement\Expression\Predicate\RegexpGenerator;
use Tests\Generator\MySql\Statement\Expression\Predicate\SoundsLikeGenerator;
use Tests\Generator\MySql\Statement\Expression\RowGenerator;
use Tests\Generator\MySql\Statement\Expression\Subquery\ExistsGenerator;
use Tests\Generator\MySql\Statement\Expression\Subquery\InQueryGenerator;
use Tests\Generator\MySql\Statement\Expression\Subquery\QuantifiedComparisonGenerator;
use Tests\Generator\MySql\Statement\Expression\Subquery\RowSubqueryGenerator;
use Tests\Generator\MySql\Statement\Expression\Subquery\ScalarSubqueryGenerator;
use Tests\Generator\MySql\Statement\Expression\TruthTestGenerator;
use Tests\Generator\MySql\Statement\Literal\BooleanLiteralGenerator;
use Tests\Generator\MySql\Statement\Literal\NullLiteralGenerator;
use Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator;
use Tests\Generator\MySql\Statement\Literal\ParameterGenerator;
use Tests\Generator\MySql\Statement\Literal\RadixLiteralGenerator;
use Tests\Generator\MySql\Statement\Literal\SignedLiteralGenerator;
use Tests\Generator\MySql\Statement\Literal\StringLiteralGenerator;
use Tests\Generator\MySql\Statement\Literal\TemporalLiteralGenerator;
use Tests\Generator\MySql\Statement\Name\ColumnUseGenerator;
use Tests\Generator\MySql\Statement\Query\Clause\OutputOrdinalGenerator;
use Tests\Generator\MySql\Statement\Query\Clause\ProgramVariableGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Access\DeferredFullTextSearchGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Branching\DeferredCaseGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Call\Aggregate\DeferredGroupConcatGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Call\Aggregate\DeferredJsonObjectAggregateGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Call\DeferredAggregateGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Call\DeferredCharGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Call\DeferredDateArithmeticGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Call\DeferredExtractGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Call\DeferredGetFormatGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Call\DeferredKeywordCallGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Call\DeferredPositionGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Call\DeferredTimestampGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Call\DeferredTrimGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Call\DeferredWeightParametersGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Call\DeferredWeightStringGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Call\DeferredWindowAggregateGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Call\DeferredWindowFunctionGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Call\DormantCallGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Call\Json\DeferredJsonValueCallGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Constructor\DeferredRowGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Conversion\DeferredBinaryCastGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Conversion\DeferredCastGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Conversion\DeferredCharsetConversionGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredArithmeticGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredCallGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredComparisonGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredGroupedGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredLikeGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredMemberOfGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredMembershipGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredSubqueryGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredSubqueryPredicateGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredTimeZoneGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredTruthTestGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Operator\DeferredAssignmentGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Operator\DeferredCollatedGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Operator\DeferredIntervalGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Operator\DeferredLogicalGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Operator\DeferredNotGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Operator\DeferredNullTestGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Operator\DeferredUnaryGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Predicate\DeferredBetweenGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Predicate\DeferredRegexpGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Predicate\DeferredSoundsLikeGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Time\DeferredClockGenerator;
use Tests\Generator\MySql\Statement\Server\Plugin\OnWordGenerator;
use Tests\Generator\MySql\Statement\Table\Key\ArrayPartGenerator;
use Tests\Generator\MySql\Statement\Utility\Set\BareNameGenerator;
use Tests\Generator\MySql\Statement\Variable\SystemTargetGenerator;
use Tests\Generator\MySql\Statement\Variable\SystemVariableGenerator;
use Tests\Generator\MySql\Statement\Variable\UserVariableGenerator;
use Tests\Generator\MySql\Statement\Variable\VariableAssignmentGenerator;

/**
 * Generates Operand values from the generators of its operands.
 *
 * @implements ErisGenerator<Operand>
 */
final class OperandGenerator implements ErisGenerator
{
    /** @var ErisGenerator<Operand> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $this->delegate = $depth > 0
            ? Generators::bind(Generators::elements(array_keys(array_filter([511, 511, 511, 511, 511, 511, 511, 508, 511, 511, 511, 511, 511, 511, 511, 511, 511, 508, 511, 511, 511, 511, 511, 511, 511, 511, 508, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 0, 511, 511, 511, 511, 511, 511, 508, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 508, 508, 511, 508, 511, 511, 511, 511, 511, 511, 511, 511, 511, 508, 511, 511, 511, 508, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 508, 511, 511, 511, 511, 511], static fn (int $mask): bool => ($bit & $mask) !== 0))), static fn (int $choice): ErisGenerator => match ($choice) {
                0 => new AggregateGenerator($release, $depth - 1),
                1 => new GroupConcatGenerator($release, $depth - 1),
                2 => new JsonObjectAggregateGenerator($release, $depth - 1),
                3 => new CharCallGenerator($release, $depth - 1),
                4 => new ClockCallGenerator($release),
                5 => new ExtractGenerator($release, $depth - 1),
                6 => new FunctionCallGenerator($release, $depth - 1),
                7 => new JsonValueCallGenerator($release, $depth - 1),
                8 => new KeywordCallGenerator($release, $depth - 1),
                9 => new PositionGenerator($release, $depth - 1),
                10 => new DateArithmeticGenerator($release, $depth - 1),
                11 => new GetFormatGenerator($release, $depth - 1),
                12 => new TimestampCallGenerator($release, $depth - 1),
                13 => new TrimGenerator($release, $depth - 1),
                14 => new WeightStringGenerator($release, $depth - 1),
                15 => new WeightStringParametersGenerator($release, $depth - 1),
                16 => new RoutineVariableGenerator($release),
                17 => new WindowFunctionGenerator($release, $depth - 1),
                18 => new DefaultRequestGenerator($release),
                19 => new DefaultOfColumnGenerator($release),
                20 => new FullTextSearchGenerator($release, $depth - 1),
                21 => new InsertedColumnGenerator($release),
                22 => new JsonExtractionGenerator($release),
                23 => new OdbcEscapeGenerator($release, $depth - 1),
                24 => new CaseExpressionGenerator($release, $depth - 1),
                25 => new ComparisonGenerator($release, $depth - 1),
                26 => new AtTimeZoneGenerator($release, $depth - 1),
                27 => new CastGenerator($release, $depth - 1),
                28 => new CharsetConversionGenerator($release, $depth - 1),
                29 => new GroupedGenerator($release, $depth - 1),
                30 => new GroupedRowGenerator($release, $depth - 1),
                31 => new LogicalGenerator($release, $depth - 1),
                32 => new NotGenerator($release, $depth - 1),
                33 => new NullTestGenerator($release, $depth - 1),
                34 => new ArithmeticGenerator($release, $depth - 1),
                35 => new BinaryCastGenerator($release, $depth - 1),
                36 => new CollatedGenerator($release, $depth - 1),
                37 => new ConcatenationGenerator($release, $depth - 1),
                38 => new IntervalAdditionGenerator($release, $depth - 1),
                39 => new IntervalArithmeticGenerator($release, $depth - 1),
                40 => new UnaryGenerator($release, $depth - 1),
                41 => new BetweenGenerator($release, $depth - 1),
                42 => new InListGenerator($release, $depth - 1),
                43 => new LikeGenerator($release, $depth - 1),
                44 => new MemberOfGenerator($release, $depth - 1),
                45 => new RegexpGenerator($release, $depth - 1),
                46 => new SoundsLikeGenerator($release, $depth - 1),
                47 => new RowGenerator($release, $depth - 1),
                48 => new ExistsGenerator($release, $depth - 1),
                49 => new InQueryGenerator($release, $depth - 1),
                50 => new QuantifiedComparisonGenerator($release, $depth - 1),
                51 => new RowSubqueryGenerator($release, $depth - 1),
                52 => new ScalarSubqueryGenerator($release, $depth - 1),
                53 => new TruthTestGenerator($release, $depth - 1),
                54 => new BooleanLiteralGenerator($release),
                55 => new NullLiteralGenerator($release),
                56 => new NumberLiteralGenerator($release),
                57 => new ParameterGenerator($release),
                58 => new RadixLiteralGenerator($release),
                59 => new SignedLiteralGenerator($release),
                60 => new StringLiteralGenerator($release),
                61 => new TemporalLiteralGenerator($release),
                62 => new ColumnUseGenerator($release),
                63 => new OutputOrdinalGenerator($release, $depth - 1),
                64 => new ProgramVariableGenerator($release),
                65 => new DeferredFullTextSearchGenerator($release, $depth - 1),
                66 => new DeferredCaseGenerator($release, $depth - 1),
                67 => new DeferredGroupConcatGenerator($release, $depth - 1),
                68 => new DeferredJsonObjectAggregateGenerator($release, $depth - 1),
                69 => new DeferredAggregateGenerator($release, $depth - 1),
                70 => new DeferredCharGenerator($release, $depth - 1),
                71 => new DeferredDateArithmeticGenerator($release, $depth - 1),
                72 => new DeferredExtractGenerator($release, $depth - 1),
                73 => new DeferredGetFormatGenerator($release, $depth - 1),
                74 => new DeferredKeywordCallGenerator($release, $depth - 1),
                75 => new DeferredPositionGenerator($release, $depth - 1),
                76 => new DeferredTimestampGenerator($release, $depth - 1),
                77 => new DeferredTrimGenerator($release, $depth - 1),
                78 => new DeferredWeightParametersGenerator($release, $depth - 1),
                79 => new DeferredWeightStringGenerator($release, $depth - 1),
                80 => new DeferredWindowAggregateGenerator($release, $depth - 1),
                81 => new DeferredWindowFunctionGenerator($release, $depth - 1),
                82 => new DormantCallGenerator($release, $depth - 1),
                83 => new DeferredJsonValueCallGenerator($release, $depth - 1),
                84 => new DeferredRowGenerator($release, $depth - 1),
                85 => new DeferredBinaryCastGenerator($release, $depth - 1),
                86 => new DeferredCastGenerator($release, $depth - 1),
                87 => new DeferredCharsetConversionGenerator($release, $depth - 1),
                88 => new DeferredArithmeticGenerator($release, $depth - 1),
                89 => new DeferredCallGenerator($release, $depth - 1),
                90 => new DeferredComparisonGenerator($release, $depth - 1),
                91 => new DeferredGroupedGenerator($release, $depth - 1),
                92 => new DeferredLikeGenerator($release, $depth - 1),
                93 => new DeferredMemberOfGenerator($release, $depth - 1),
                94 => new DeferredMembershipGenerator($release, $depth - 1),
                95 => new DeferredSubqueryGenerator($release, $depth - 1),
                96 => new DeferredSubqueryPredicateGenerator($release, $depth - 1),
                97 => new DeferredTimeZoneGenerator($release, $depth - 1),
                98 => new DeferredTruthTestGenerator($release, $depth - 1),
                99 => new DeferredAssignmentGenerator($release, $depth - 1),
                100 => new DeferredCollatedGenerator($release, $depth - 1),
                101 => new DeferredIntervalGenerator($release, $depth - 1),
                102 => new DeferredLogicalGenerator($release, $depth - 1),
                103 => new DeferredNotGenerator($release, $depth - 1),
                104 => new DeferredNullTestGenerator($release, $depth - 1),
                105 => new DeferredUnaryGenerator($release, $depth - 1),
                106 => new DeferredBetweenGenerator($release, $depth - 1),
                107 => new DeferredRegexpGenerator($release, $depth - 1),
                108 => new DeferredSoundsLikeGenerator($release, $depth - 1),
                109 => new DeferredClockGenerator($release),
                110 => new OnWordGenerator($release),
                111 => new ArrayPartGenerator($release, $depth - 1),
                112 => new BareNameGenerator($release),
                113 => new SystemTargetGenerator($release),
                114 => new SystemVariableGenerator($release),
                115 => new UserVariableGenerator($release),
                116 => new VariableAssignmentGenerator($release, $depth - 1),
                default => throw new LogicException('Unreachable alternative.'),
            })
            : Generators::bind(Generators::choose(0, 21), static fn (int $choice): ErisGenerator => match ($choice) {
                0 => new ClockCallGenerator($release),
                1 => new RoutineVariableGenerator($release),
                2 => new DefaultRequestGenerator($release),
                3 => new DefaultOfColumnGenerator($release),
                4 => new InsertedColumnGenerator($release),
                5 => new JsonExtractionGenerator($release),
                6 => new BooleanLiteralGenerator($release),
                7 => new NullLiteralGenerator($release),
                8 => new NumberLiteralGenerator($release),
                9 => new ParameterGenerator($release),
                10 => new RadixLiteralGenerator($release),
                11 => new SignedLiteralGenerator($release),
                12 => new StringLiteralGenerator($release),
                13 => new TemporalLiteralGenerator($release),
                14 => new ColumnUseGenerator($release),
                15 => new ProgramVariableGenerator($release),
                16 => new DeferredClockGenerator($release),
                17 => new OnWordGenerator($release),
                18 => new BareNameGenerator($release),
                19 => new SystemTargetGenerator($release),
                20 => new SystemVariableGenerator($release),
                21 => new UserVariableGenerator($release),
                default => throw new LogicException('Unreachable alternative.'),
            });
        $this->delegate = Generators::suchThat(
            static function (Operand $value) use ($release): bool {
                $domain = new ReleaseDomain();
                $transition = (new Generations())->transition($value);

                return ($domain->of($value) & $domain->bit($release)) !== 0
                    && (in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) ? !(new ModernForms())->requires($value) : !(new LegacyForms())->requires($value))
                    && ($transition === 0 || ($transition & ($release === GrammarRelease::MySql5651 ? 1 : 2)) !== 0)
                    && ((new LexicalModes())->requirement($value) & 10) === 0;
            },
            $this->delegate
        );
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValue<Operand>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<Operand> $element The generated counterexample
     * @return GeneratedValue<Operand>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
