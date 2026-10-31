<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Type;

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
use SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords;
use SqlSemantics\Platform\MySql\Statement\Type\CastTarget;
use SqlSemantics\Platform\MySql\Statement\Type\Keyword\CastAlias;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;
use Tests\Generator\MySql\Statement\Type\Kind\CastKindGenerator;

/**
 * Generates CastTarget values from the generators of its operands.
 *
 * @implements ErisGenerator<CastTarget>
 */
final class CastTargetGenerator implements ErisGenerator
{
    /** @var ErisGenerator<CastTarget> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of CastTarget from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::bind(
            Generators::tuple(new CastKindGenerator($release), Generators::bool()),
            static function (array $selection) use ($release): ErisGenerator {
                [$kind, $optionalWords] = $selection;
                $words = $optionalWords && in_array($kind, [CastKind::Signed, CastKind::Unsigned, CastKind::Double, CastKind::NationalChar], true) ? OptionalWords::Written : OptionalWords::Omitted;
                $aliases = array_values(array_filter(CastAlias::cases(), static fn (CastAlias $alias): bool => $alias->kind() === $kind && $alias->words() === $words));
                $lengths = in_array($kind, [CastKind::Binary, CastKind::Char, CastKind::NationalChar, CastKind::Time, CastKind::DateTime, CastKind::Decimal, CastKind::Float], true)
                    ? Generators::oneOf(Generators::constant(null), Generators::map(static fn (int $length): string => (string) $length, Generators::choose(1, in_array($kind, [CastKind::Time, CastKind::DateTime], true) ? 6 : 30)))
                    : Generators::constant(null);

                return Generators::bind($lengths, static fn (?string $length): ErisGenerator => Generators::map(
                    static fn (array $parts): CastTarget => new CastTarget(kind: $kind, length: $length, scale: $parts[0], charset: $parts[1], words: $words, inputSource: null, alias: $parts[2]),
                    Generators::tuple(
                        $kind === CastKind::Decimal && $length !== null ? Generators::oneOf(Generators::constant(null), Generators::map(static fn (int $scale): string => (string) $scale, Generators::choose(0, 10))) : Generators::constant(null),
                        $kind === CastKind::Char ? Generators::oneOf(Generators::constant(null), new CharsetAttributeGenerator($release)) : Generators::constant(null),
                        $aliases === [] ? Generators::constant(null) : Generators::oneOf(Generators::constant(null), Generators::elements($aliases))
                    )
                ));
            }
        );
        $this->delegate = Generators::suchThat(
            static function (CastTarget $value) use ($release): bool {
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
     * @return GeneratedValueSingle<CastTarget>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<CastTarget>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
