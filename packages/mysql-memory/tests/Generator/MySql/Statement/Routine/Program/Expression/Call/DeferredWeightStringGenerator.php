<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Expression\Call;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightCast;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredWeightString;
use Tests\Generator\MySql\Statement\Call\Weight\WeightCastGenerator;
use Tests\Generator\MySql\Statement\Call\Weight\WeightLevelGenerator;
use Tests\Generator\MySql\Statement\Call\Weight\WeightLevelRangeGenerator;
use Tests\Generator\MySql\Statement\Expression\RowOperandGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredOperandGenerator;

/**
 * Generates DeferredWeightString values from the generators of its operands.
 *
 * @implements ErisGenerator<DeferredWeightString>
 */
final class DeferredWeightStringGenerator implements ErisGenerator
{
    /** @var ErisGenerator<DeferredWeightString> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::bind(
            Generators::oneOf(Generators::constant(null), new WeightCastGenerator($release)),
            static fn (?WeightCast $cast): ErisGenerator => Generators::map(
                static fn (array $parts): DeferredWeightString => new DeferredWeightString(subject: $parts[0], cast: $cast, length: $parts[1], levels: $parts[2] === 0 ? $parts[3] : [], range: $parts[2] === 1 ? $parts[4] : null, scalarInformation: null, inputSource: null),
                Generators::tuple(
                    Generators::bind(Generators::bool(), static fn (bool $row): ErisGenerator => $row ? new RowOperandGenerator($release, $depth - 1) : new DeferredOperandGenerator($release, $depth - 1)),
                    $cast === null ? Generators::constant(null) : Generators::map(static fn (int $length): Numeral => new Numeral((string) $length), Generators::choose(1, 99)),
                    $cast === WeightCast::Binary || ((new ReleaseDomain())->bit($release) & 3) === 0 ? Generators::constant(2) : Generators::choose(0, 2),
                    ((new ReleaseDomain())->bit($release) & 3) === 0 ? Generators::constant([]) : Generators::bind(Generators::choose(1, 3), static fn (int $count): ErisGenerator => Generators::vector($count, new WeightLevelGenerator($release))),
                    ((new ReleaseDomain())->bit($release) & 3) === 0 ? Generators::constant(null) : new WeightLevelRangeGenerator($release)
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (DeferredWeightString $value) use ($release): bool {
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
     * @return GeneratedValue<DeferredWeightString>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<DeferredWeightString> $element The generated counterexample
     * @return GeneratedValue<DeferredWeightString>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
