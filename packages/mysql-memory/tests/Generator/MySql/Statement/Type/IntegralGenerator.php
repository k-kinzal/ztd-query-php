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
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Keyword\IntegralAlias;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use Tests\Generator\MySql\Statement\Type\Kind\IntegralKindGenerator;
use Tests\Generator\MySql\Statement\Type\Kind\NumericModifierGenerator;

/**
 * Generates Integral values from the generators of its operands.
 *
 * @implements ErisGenerator<Integral>
 */
final class IntegralGenerator implements ErisGenerator
{
    /** @var ErisGenerator<Integral> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::bind(
            new IntegralKindGenerator($release),
            static function (IntegralKind $kind) use ($release): ErisGenerator {
                $aliases = array_values(array_filter(IntegralAlias::cases(), static fn (IntegralAlias $alias): bool => $alias->kind() === $kind));

                return Generators::map(
                    static fn (array $parts): Integral => new Integral(kind: $kind, width: $parts[0], modifiers: $parts[1], inputSource: null, alias: $parts[2]),
                    Generators::tuple(
                        Generators::oneOf(Generators::constant(null), Generators::map(static fn (int $number): string => (string) $number, Generators::choose(0, 99))),
                        Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new NumericModifierGenerator($release))),
                        $aliases === [] ? Generators::constant(null) : Generators::oneOf(Generators::constant(null), Generators::elements($aliases))
                    )
                );
            }
        );
        $this->delegate = Generators::suchThat(
            static function (Integral $value) use ($release): bool {
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
     * @return GeneratedValue<Integral>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<Integral> $element The generated counterexample
     * @return GeneratedValue<Integral>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
