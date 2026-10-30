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
use SqlSemantics\Platform\MySql\Statement\Literal\Radix;
use SqlSemantics\Platform\MySql\Statement\Literal\RadixLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\RadixNotation;

/**
 * Generates RadixLiteral values from the generators of its operands.
 *
 * @implements ErisGenerator<RadixLiteral>
 */
final class RadixLiteralGenerator implements ErisGenerator
{
    /** @var ErisGenerator<RadixLiteral> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::bind(
            Generators::tuple(new RadixGenerator($release), Generators::oneOf(Generators::constant(null), new RadixNotationGenerator($release))),
            static function (array $head): ErisGenerator {
                [$radix, $notation] = $head;
                $digit = $radix === Radix::Hexadecimal ? Generators::elements(str_split('0123456789abcdefABCDEF')) : Generators::elements(['0', '1']);
                $pairs = $radix === Radix::Hexadecimal && $notation !== RadixNotation::Prefixed;

                return Generators::map(
                    static fn (array $digits): RadixLiteral => new RadixLiteral(radix: $radix, digits: implode('', $digits), introducer: null, scalarInformation: null, inputSource: null, notation: $notation),
                    Generators::bind(Generators::choose($notation === RadixNotation::Prefixed ? 1 : 0, 4), static fn (int $count): ErisGenerator => Generators::vector($pairs ? $count * 2 : $count, $digit))
                );
            }
        );
        $this->delegate = Generators::suchThat(
            static function (RadixLiteral $value) use ($release): bool {
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
     * @return GeneratedValue<RadixLiteral>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<RadixLiteral> $element The generated counterexample
     * @return GeneratedValue<RadixLiteral>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
