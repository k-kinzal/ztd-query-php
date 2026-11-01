<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Temporal;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Call\Temporal\DateArithmetic;
use Tests\Generator\MySql\Statement\Call\Keyword\DateArithmeticAliasGenerator;
use Tests\Generator\MySql\Statement\Expression\IntervalUnitGenerator;
use Tests\Generator\Semantics\Statement\ScalarGenerator;

/**
 * Generates DateArithmetic values from the generators of its operands.
 *
 * @implements ErisGenerator<DateArithmetic>
 */
final class DateArithmeticGenerator implements ErisGenerator
{
    /** @var ErisGenerator<DateArithmetic> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of DateArithmetic from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): DateArithmetic => new DateArithmetic(subtract: $parts[0], date: $parts[1], quantity: $parts[2], unit: $parts[3], scalarInformation: $parts[4], inputSource: $parts[5], alias: $parts[6] !== null && $parts[6]->subtracts() === $parts[0] ? $parts[6] : null),
            Generators::tuple(
                Generators::bool(),
                new ScalarGenerator($release, $depth - 1),
                new ScalarGenerator($release, $depth - 1),
                new IntervalUnitGenerator($release),
                Generators::constant(null),
                Generators::constant(null),
                Generators::oneOf(Generators::constant(null), new DateArithmeticAliasGenerator($release))
            )
        );
        $this->delegate = Generators::suchThat(
            static function (DateArithmetic $value) use ($release): bool {
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
     * @return GeneratedValueSingle<DateArithmetic>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<DateArithmetic>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
