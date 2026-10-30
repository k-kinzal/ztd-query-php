<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call;

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
use SqlSemantics\Platform\MySql\Statement\Call\Clock;
use SqlSemantics\Platform\MySql\Statement\Call\ClockCall;
use SqlSemantics\Platform\MySql\Statement\Call\Keyword\ClockAlias;
use SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator;

/**
 * Generates ClockCall values from the generators of its operands.
 *
 * @implements ErisGenerator<ClockCall>
 */
final class ClockCallGenerator implements ErisGenerator
{
    /** @var ErisGenerator<ClockCall> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::bind(
            new ClockGenerator($release),
            static function (Clock $clock) use ($release): ErisGenerator {
                $aliases = array_values(array_filter(ClockAlias::cases(), static fn (ClockAlias $alias): bool => $alias->clock() === $clock));

                return Generators::map(
                    static fn (array $parts): ClockCall => new ClockCall(clock: $clock, precision: $parts[0], parentheses: $parts[0] === null && $clock->bare() !== null ? $parts[1] : OptionalWords::Written, scalarInformation: null, inputSource: null, alias: $parts[2]),
                    Generators::tuple(
                        $clock->precise() ? Generators::oneOf(Generators::constant(null), Generators::map(static fn (int $digits): Numeral => new Numeral((string) $digits), Generators::choose(0, 6))) : Generators::constant(null),
                        new OptionalWordsGenerator($release),
                        $aliases === [] ? Generators::constant(null) : Generators::oneOf(Generators::constant(null), Generators::elements($aliases))
                    )
                );
            }
        );
        $this->delegate = Generators::suchThat(
            static function (ClockCall $value) use ($release): bool {
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
     * @return GeneratedValue<ClockCall>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<ClockCall> $element The generated counterexample
     * @return GeneratedValue<ClockCall>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
