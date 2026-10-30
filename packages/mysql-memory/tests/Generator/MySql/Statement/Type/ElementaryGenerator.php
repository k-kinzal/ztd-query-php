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
use SqlSemantics\Platform\MySql\Statement\Type\Elementary;
use SqlSemantics\Platform\MySql\Statement\Type\Keyword\ElementaryAlias;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind;
use Tests\Generator\MySql\Statement\Type\Kind\ElementaryKindGenerator;

/**
 * Generates Elementary values from the generators of its operands.
 *
 * @implements ErisGenerator<Elementary>
 */
final class ElementaryGenerator implements ErisGenerator
{
    /** @var ErisGenerator<Elementary> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::bind(
            new ElementaryKindGenerator($release),
            static function (ElementaryKind $kind): ErisGenerator {
                $aliases = array_values(array_filter(ElementaryAlias::cases(), static fn (ElementaryAlias $alias): bool => $alias->kind() === $kind));

                return Generators::map(
                    static fn (array $parts): Elementary => new Elementary(kind: $kind, length: $parts[0], inputSource: null, alias: $parts[1]),
                    Generators::tuple(
                        in_array($kind, [ElementaryKind::Bit, ElementaryKind::Vector], true) ? Generators::oneOf(Generators::constant(null), Generators::map(static fn (int $length): string => (string) $length, Generators::choose(1, 64))) : Generators::constant(null),
                        $aliases === [] ? Generators::constant(null) : Generators::oneOf(Generators::constant(null), Generators::elements($aliases))
                    )
                );
            }
        );
        $this->delegate = Generators::suchThat(
            static function (Elementary $value) use ($release): bool {
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
     * @return GeneratedValue<Elementary>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<Elementary> $element The generated counterexample
     * @return GeneratedValue<Elementary>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
