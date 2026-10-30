<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Expression;

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
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
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

/**
 * Generates DeferredOperand values from the generators of its operands.
 *
 * @implements ErisGenerator<DeferredOperand>
 */
final class DeferredOperandGenerator implements ErisGenerator
{
    /** @var ErisGenerator<DeferredOperand> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $this->delegate = $depth > 0
            ? Generators::bind(Generators::elements(array_keys(array_filter([511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 508, 508, 511, 508, 511, 511, 511, 511, 511, 511, 511, 511, 511, 508, 511, 511, 511, 508, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511], static fn (int $mask): bool => ($bit & $mask) !== 0))), static fn (int $choice): ErisGenerator => match ($choice) {
                0 => new DeferredFullTextSearchGenerator($release, $depth - 1),
                1 => new DeferredCaseGenerator($release, $depth - 1),
                2 => new DeferredGroupConcatGenerator($release, $depth - 1),
                3 => new DeferredJsonObjectAggregateGenerator($release, $depth - 1),
                4 => new DeferredAggregateGenerator($release, $depth - 1),
                5 => new DeferredCharGenerator($release, $depth - 1),
                6 => new DeferredDateArithmeticGenerator($release, $depth - 1),
                7 => new DeferredExtractGenerator($release, $depth - 1),
                8 => new DeferredGetFormatGenerator($release, $depth - 1),
                9 => new DeferredKeywordCallGenerator($release, $depth - 1),
                10 => new DeferredPositionGenerator($release, $depth - 1),
                11 => new DeferredTimestampGenerator($release, $depth - 1),
                12 => new DeferredTrimGenerator($release, $depth - 1),
                13 => new DeferredWeightParametersGenerator($release, $depth - 1),
                14 => new DeferredWeightStringGenerator($release, $depth - 1),
                15 => new DeferredWindowAggregateGenerator($release, $depth - 1),
                16 => new DeferredWindowFunctionGenerator($release, $depth - 1),
                17 => new DormantCallGenerator($release, $depth - 1),
                18 => new DeferredJsonValueCallGenerator($release, $depth - 1),
                19 => new DeferredRowGenerator($release, $depth - 1),
                20 => new DeferredBinaryCastGenerator($release, $depth - 1),
                21 => new DeferredCastGenerator($release, $depth - 1),
                22 => new DeferredCharsetConversionGenerator($release, $depth - 1),
                23 => new DeferredArithmeticGenerator($release, $depth - 1),
                24 => new DeferredCallGenerator($release, $depth - 1),
                25 => new DeferredComparisonGenerator($release, $depth - 1),
                26 => new DeferredGroupedGenerator($release, $depth - 1),
                27 => new DeferredLikeGenerator($release, $depth - 1),
                28 => new DeferredMemberOfGenerator($release, $depth - 1),
                29 => new DeferredMembershipGenerator($release, $depth - 1),
                30 => new DeferredSubqueryGenerator($release, $depth - 1),
                31 => new DeferredSubqueryPredicateGenerator($release, $depth - 1),
                32 => new DeferredTimeZoneGenerator($release, $depth - 1),
                33 => new DeferredTruthTestGenerator($release, $depth - 1),
                34 => new DeferredAssignmentGenerator($release, $depth - 1),
                35 => new DeferredCollatedGenerator($release, $depth - 1),
                36 => new DeferredIntervalGenerator($release, $depth - 1),
                37 => new DeferredLogicalGenerator($release, $depth - 1),
                38 => new DeferredNotGenerator($release, $depth - 1),
                39 => new DeferredNullTestGenerator($release, $depth - 1),
                40 => new DeferredUnaryGenerator($release, $depth - 1),
                41 => new DeferredBetweenGenerator($release, $depth - 1),
                42 => new DeferredRegexpGenerator($release, $depth - 1),
                43 => new DeferredSoundsLikeGenerator($release, $depth - 1),
                44 => new DeferredClockGenerator($release),
                default => throw new LogicException('Unreachable alternative.'),
            })
            : Generators::bind(Generators::choose(0, 0), static fn (int $choice): ErisGenerator => match ($choice) {
                0 => new DeferredClockGenerator($release),
                default => throw new LogicException('Unreachable alternative.'),
            });
        $this->delegate = Generators::suchThat(
            static function (DeferredOperand $value) use ($release): bool {
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
     * @return GeneratedValue<DeferredOperand>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<DeferredOperand> $element The generated counterexample
     * @return GeneratedValue<DeferredOperand>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
