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
use SqlSemantics\Statement\Scalar;
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
use Tests\Generator\MySql\Statement\Call\Window\WindowFunctionGenerator;
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
use Tests\Generator\MySql\Statement\Expression\Subquery\ExistsGenerator;
use Tests\Generator\MySql\Statement\Expression\Subquery\InQueryGenerator;
use Tests\Generator\MySql\Statement\Expression\Subquery\QuantifiedComparisonGenerator;
use Tests\Generator\MySql\Statement\Expression\Subquery\ScalarSubqueryGenerator;
use Tests\Generator\MySql\Statement\Expression\TruthTestGenerator;
use Tests\Generator\MySql\Statement\Literal\BooleanLiteralGenerator;
use Tests\Generator\MySql\Statement\Literal\NullLiteralGenerator;
use Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator;
use Tests\Generator\MySql\Statement\Literal\ParameterGenerator;
use Tests\Generator\MySql\Statement\Literal\RadixLiteralGenerator;
use Tests\Generator\MySql\Statement\Literal\StringLiteralGenerator;
use Tests\Generator\MySql\Statement\Literal\TemporalLiteralGenerator;
use Tests\Generator\MySql\Statement\Name\ColumnUseGenerator;
use Tests\Generator\MySql\Statement\Variable\SystemVariableGenerator;
use Tests\Generator\MySql\Statement\Variable\UserVariableGenerator;
use Tests\Generator\MySql\Statement\Variable\VariableAssignmentGenerator;

/**
 * Generates Scalar values from the generators of its operands.
 *
 * @implements ErisGenerator<Scalar>
 */
final class ScalarGenerator implements ErisGenerator
{
    /** @var ErisGenerator<Scalar> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $this->delegate = $depth > 0
            ? Generators::bind(Generators::elements(array_keys(array_filter([511, 511, 511, 511, 511, 511, 511, 508, 511, 511, 511, 511, 511, 511, 511, 511, 508, 511, 511, 511, 511, 511, 511, 511, 508, 511, 511, 511, 511, 511, 511, 511, 511, 511, 0, 511, 511, 511, 511, 511, 511, 508, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511], static fn (int $mask): bool => ($bit & $mask) !== 0))), static fn (int $choice): ErisGenerator => match ($choice) {
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
                16 => new WindowFunctionGenerator($release, $depth - 1),
                17 => new DefaultOfColumnGenerator($release),
                18 => new FullTextSearchGenerator($release, $depth - 1),
                19 => new InsertedColumnGenerator($release),
                20 => new JsonExtractionGenerator($release),
                21 => new OdbcEscapeGenerator($release, $depth - 1),
                22 => new CaseExpressionGenerator($release, $depth - 1),
                23 => new ComparisonGenerator($release, $depth - 1),
                24 => new AtTimeZoneGenerator($release, $depth - 1),
                25 => new CastGenerator($release, $depth - 1),
                26 => new CharsetConversionGenerator($release, $depth - 1),
                27 => new GroupedGenerator($release, $depth - 1),
                28 => new LogicalGenerator($release, $depth - 1),
                29 => new NotGenerator($release, $depth - 1),
                30 => new NullTestGenerator($release, $depth - 1),
                31 => new ArithmeticGenerator($release, $depth - 1),
                32 => new BinaryCastGenerator($release, $depth - 1),
                33 => new CollatedGenerator($release, $depth - 1),
                34 => new ConcatenationGenerator($release, $depth - 1),
                35 => new IntervalAdditionGenerator($release, $depth - 1),
                36 => new IntervalArithmeticGenerator($release, $depth - 1),
                37 => new UnaryGenerator($release, $depth - 1),
                38 => new BetweenGenerator($release, $depth - 1),
                39 => new InListGenerator($release, $depth - 1),
                40 => new LikeGenerator($release, $depth - 1),
                41 => new MemberOfGenerator($release, $depth - 1),
                42 => new RegexpGenerator($release, $depth - 1),
                43 => new SoundsLikeGenerator($release, $depth - 1),
                44 => new ExistsGenerator($release, $depth - 1),
                45 => new InQueryGenerator($release, $depth - 1),
                46 => new QuantifiedComparisonGenerator($release, $depth - 1),
                47 => new ScalarSubqueryGenerator($release, $depth - 1),
                48 => new TruthTestGenerator($release, $depth - 1),
                49 => new BooleanLiteralGenerator($release),
                50 => new NullLiteralGenerator($release),
                51 => new NumberLiteralGenerator($release),
                52 => new ParameterGenerator($release),
                53 => new RadixLiteralGenerator($release),
                54 => new StringLiteralGenerator($release),
                55 => new TemporalLiteralGenerator($release),
                56 => new ColumnUseGenerator($release),
                57 => new SystemVariableGenerator($release),
                58 => new UserVariableGenerator($release),
                59 => new VariableAssignmentGenerator($release, $depth - 1),
                default => throw new LogicException('Unreachable alternative.'),
            })
            : Generators::bind(Generators::choose(0, 13), static fn (int $choice): ErisGenerator => match ($choice) {
                0 => new ClockCallGenerator($release),
                1 => new DefaultOfColumnGenerator($release),
                2 => new InsertedColumnGenerator($release),
                3 => new JsonExtractionGenerator($release),
                4 => new BooleanLiteralGenerator($release),
                5 => new NullLiteralGenerator($release),
                6 => new NumberLiteralGenerator($release),
                7 => new ParameterGenerator($release),
                8 => new RadixLiteralGenerator($release),
                9 => new StringLiteralGenerator($release),
                10 => new TemporalLiteralGenerator($release),
                11 => new ColumnUseGenerator($release),
                12 => new SystemVariableGenerator($release),
                13 => new UserVariableGenerator($release),
                default => throw new LogicException('Unreachable alternative.'),
            });
        $this->delegate = Generators::suchThat(
            static function (Scalar $value) use ($release): bool {
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
     * @return GeneratedValue<Scalar>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<Scalar> $element The generated counterexample
     * @return GeneratedValue<Scalar>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
