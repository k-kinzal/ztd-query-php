<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Statement\Expression;
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
 * Generates Expression values from the generators of its operands.
 *
 * @implements ErisGenerator<Expression>
 */
final class ExpressionGenerator implements ErisGenerator
{
    /** @var ErisGenerator<Expression> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of Expression from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $this->delegate = Generators::bind(Generators::elements(array_keys(array_filter([511, 511, 511, 511, 511, 511, 511, 508, 511, 511, 511, 511, 511, 511, 511, 511, 511, 508, 511, 511, 511, 511, 511, 511, 511, 511, 508, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 0, 511, 511, 511, 511, 511, 511, 508, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 508, 508, 511, 508, 511, 511, 511, 511, 511, 511, 511, 511, 511, 508, 511, 511, 511, 508, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 508, 511, 511, 511, 511, 511], static fn (int $mask): bool => ($bit & $mask) !== 0))), static fn (int $choice): ErisGenerator => match ($choice) {
            0 => new AggregateGenerator($release),
            1 => new GroupConcatGenerator($release),
            2 => new JsonObjectAggregateGenerator($release),
            3 => new CharCallGenerator($release),
            4 => new ClockCallGenerator($release),
            5 => new ExtractGenerator($release),
            6 => new FunctionCallGenerator($release),
            7 => new JsonValueCallGenerator($release),
            8 => new KeywordCallGenerator($release),
            9 => new PositionGenerator($release),
            10 => new DateArithmeticGenerator($release),
            11 => new GetFormatGenerator($release),
            12 => new TimestampCallGenerator($release),
            13 => new TrimGenerator($release),
            14 => new WeightStringGenerator($release),
            15 => new WeightStringParametersGenerator($release),
            16 => new RoutineVariableGenerator($release),
            17 => new WindowFunctionGenerator($release),
            18 => new DefaultRequestGenerator($release),
            19 => new DefaultOfColumnGenerator($release),
            20 => new FullTextSearchGenerator($release),
            21 => new InsertedColumnGenerator($release),
            22 => new JsonExtractionGenerator($release),
            23 => new OdbcEscapeGenerator($release),
            24 => new CaseExpressionGenerator($release),
            25 => new ComparisonGenerator($release),
            26 => new AtTimeZoneGenerator($release),
            27 => new CastGenerator($release),
            28 => new CharsetConversionGenerator($release),
            29 => new GroupedGenerator($release),
            30 => new GroupedRowGenerator($release),
            31 => new LogicalGenerator($release),
            32 => new NotGenerator($release),
            33 => new NullTestGenerator($release),
            34 => new ArithmeticGenerator($release),
            35 => new BinaryCastGenerator($release),
            36 => new CollatedGenerator($release),
            37 => new ConcatenationGenerator($release),
            38 => new IntervalAdditionGenerator($release),
            39 => new IntervalArithmeticGenerator($release),
            40 => new UnaryGenerator($release),
            41 => new BetweenGenerator($release),
            42 => new InListGenerator($release),
            43 => new LikeGenerator($release),
            44 => new MemberOfGenerator($release),
            45 => new RegexpGenerator($release),
            46 => new SoundsLikeGenerator($release),
            47 => new RowGenerator($release),
            48 => new ExistsGenerator($release),
            49 => new InQueryGenerator($release),
            50 => new QuantifiedComparisonGenerator($release),
            51 => new RowSubqueryGenerator($release),
            52 => new ScalarSubqueryGenerator($release),
            53 => new TruthTestGenerator($release),
            54 => new BooleanLiteralGenerator($release),
            55 => new NullLiteralGenerator($release),
            56 => new NumberLiteralGenerator($release),
            57 => new ParameterGenerator($release),
            58 => new RadixLiteralGenerator($release),
            59 => new SignedLiteralGenerator($release),
            60 => new StringLiteralGenerator($release),
            61 => new TemporalLiteralGenerator($release),
            62 => new ColumnUseGenerator($release),
            63 => new OutputOrdinalGenerator($release),
            64 => new ProgramVariableGenerator($release),
            65 => new DeferredFullTextSearchGenerator($release),
            66 => new DeferredCaseGenerator($release),
            67 => new DeferredGroupConcatGenerator($release),
            68 => new DeferredJsonObjectAggregateGenerator($release),
            69 => new DeferredAggregateGenerator($release),
            70 => new DeferredCharGenerator($release),
            71 => new DeferredDateArithmeticGenerator($release),
            72 => new DeferredExtractGenerator($release),
            73 => new DeferredGetFormatGenerator($release),
            74 => new DeferredKeywordCallGenerator($release),
            75 => new DeferredPositionGenerator($release),
            76 => new DeferredTimestampGenerator($release),
            77 => new DeferredTrimGenerator($release),
            78 => new DeferredWeightParametersGenerator($release),
            79 => new DeferredWeightStringGenerator($release),
            80 => new DeferredWindowAggregateGenerator($release),
            81 => new DeferredWindowFunctionGenerator($release),
            82 => new DormantCallGenerator($release),
            83 => new DeferredJsonValueCallGenerator($release),
            84 => new DeferredRowGenerator($release),
            85 => new DeferredBinaryCastGenerator($release),
            86 => new DeferredCastGenerator($release),
            87 => new DeferredCharsetConversionGenerator($release),
            88 => new DeferredArithmeticGenerator($release),
            89 => new DeferredCallGenerator($release),
            90 => new DeferredComparisonGenerator($release),
            91 => new DeferredGroupedGenerator($release),
            92 => new DeferredLikeGenerator($release),
            93 => new DeferredMemberOfGenerator($release),
            94 => new DeferredMembershipGenerator($release),
            95 => new DeferredSubqueryGenerator($release),
            96 => new DeferredSubqueryPredicateGenerator($release),
            97 => new DeferredTimeZoneGenerator($release),
            98 => new DeferredTruthTestGenerator($release),
            99 => new DeferredAssignmentGenerator($release),
            100 => new DeferredCollatedGenerator($release),
            101 => new DeferredIntervalGenerator($release),
            102 => new DeferredLogicalGenerator($release),
            103 => new DeferredNotGenerator($release),
            104 => new DeferredNullTestGenerator($release),
            105 => new DeferredUnaryGenerator($release),
            106 => new DeferredBetweenGenerator($release),
            107 => new DeferredRegexpGenerator($release),
            108 => new DeferredSoundsLikeGenerator($release),
            109 => new DeferredClockGenerator($release),
            110 => new OnWordGenerator($release),
            111 => new ArrayPartGenerator($release),
            112 => new BareNameGenerator($release),
            113 => new SystemTargetGenerator($release),
            114 => new SystemVariableGenerator($release),
            115 => new UserVariableGenerator($release),
            116 => new VariableAssignmentGenerator($release),
            default => throw new LogicException('Unreachable alternative.'),
        });
        $this->delegate = Generators::suchThat(
            static function (Expression $value) use ($release): bool {
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
     * @return GeneratedValueSingle<Expression>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<Expression>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
