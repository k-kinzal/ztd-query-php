<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Weight;

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
use SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightStringParameters;
use Tests\Generator\MySql\Statement\Literal\NumeralGenerator;
use Tests\Generator\Semantics\Statement\ScalarGenerator;

/**
 * Generates WeightStringParameters values from the generators of its operands.
 *
 * @implements ErisGenerator<WeightStringParameters>
 */
final class WeightStringParametersGenerator implements ErisGenerator
{
    /** @var ErisGenerator<WeightStringParameters> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): WeightStringParameters => new WeightStringParameters(subject: $parts[0], length: $parts[1], weights: $parts[2], flags: $parts[3], scalarInformation: $parts[4], inputSource: $parts[5]),
            Generators::tuple(
                new ScalarGenerator($release, $depth - 1),
                new NumeralGenerator($release),
                new NumeralGenerator($release),
                new NumeralGenerator($release),
                Generators::constant(null),
                Generators::constant(null)
            )
        );
        $this->delegate = Generators::suchThat(
            static function (WeightStringParameters $value) use ($release): bool {
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
     * @return GeneratedValue<WeightStringParameters>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<WeightStringParameters> $element The generated counterexample
     * @return GeneratedValue<WeightStringParameters>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
