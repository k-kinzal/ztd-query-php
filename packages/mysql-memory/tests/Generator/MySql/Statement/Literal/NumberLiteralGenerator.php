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
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;

/**
 * Generates NumberLiteral values from the generators of its operands.
 *
 * @implements ErisGenerator<NumberLiteral>
 */
final class NumberLiteralGenerator implements ErisGenerator
{
    /** @var ErisGenerator<NumberLiteral> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): NumberLiteral => new NumberLiteral(text: $parts[0], scalarInformation: null, inputSource: null),
            Generators::tuple(
                Generators::bind(Generators::choose(0, 2), static fn (int $form): ErisGenerator => match ($form) {
                    0 => Generators::map(static fn (int $number): string => (string) $number, Generators::choose(0, 65535)),
                    1 => Generators::map(static fn (array $digits): string => $digits[0] . '.' . $digits[1], Generators::tuple(Generators::choose(0, 999), Generators::choose(0, 99))),
                    default => Generators::map(static fn (array $digits): string => $digits[0] . 'e' . $digits[1], Generators::tuple(Generators::choose(0, 99), Generators::choose(0, 9))),
                })
            )
        );
        $this->delegate = Generators::suchThat(
            static function (NumberLiteral $value) use ($release): bool {
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
     * @return GeneratedValue<NumberLiteral>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<NumberLiteral> $element The generated counterexample
     * @return GeneratedValue<NumberLiteral>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
