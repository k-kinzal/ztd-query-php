<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Type;

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
use SqlSemantics\Platform\MySql\Statement\Type\Decimal;
use Tests\Generator\MySql\Statement\Type\Keyword\DecimalWordGenerator;
use Tests\Generator\MySql\Statement\Type\Kind\NumericModifierGenerator;

/**
 * Generates Decimal values from the generators of its operands.
 *
 * @implements ErisGenerator<Decimal>
 */
final class DecimalGenerator implements ErisGenerator
{
    /** @var ErisGenerator<Decimal> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): Decimal => new Decimal(precision: $parts[0], scale: $parts[0] === null ? null : $parts[1], modifiers: $parts[2], inputSource: null, word: $parts[3]),
            Generators::tuple(
                Generators::oneOf(Generators::constant(null), Generators::map(static fn (int $number): string => (string) $number, Generators::choose(0, 99))),
                Generators::oneOf(Generators::constant(null), Generators::map(static fn (int $number): string => (string) $number, Generators::choose(0, 99))),
                Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new NumericModifierGenerator($release))),
                new DecimalWordGenerator($release)
            )
        );
        $this->delegate = Generators::suchThat(
            static function (Decimal $value) use ($release): bool {
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
     * @return GeneratedValue<Decimal>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<Decimal> $element The generated counterexample
     * @return GeneratedValue<Decimal>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
