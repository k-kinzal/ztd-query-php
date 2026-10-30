<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Literal;

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
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;

/**
 * Generates Numeral values from the generators of its operands.
 *
 * @implements ErisGenerator<Numeral>
 */
final class NumeralGenerator implements ErisGenerator
{
    /** @var ErisGenerator<Numeral> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::bind(
            Generators::bool(),
            static fn (bool $hexadecimal): ErisGenerator => Generators::map(
                static fn (array $parts): Numeral => new Numeral(text: $parts[0], hexadecimal: $hexadecimal, inputSource: null, notation: null),
                Generators::tuple(
                    $hexadecimal
                        ? Generators::map(static fn (int $number): string => dechex($number), Generators::choose(0, 65535))
                        : Generators::map(static fn (int $number): string => (string) $number, Generators::choose(0, 65535))
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (Numeral $value) use ($release): bool {
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
     * @return GeneratedValue<Numeral>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<Numeral> $element The generated counterexample
     * @return GeneratedValue<Numeral>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
