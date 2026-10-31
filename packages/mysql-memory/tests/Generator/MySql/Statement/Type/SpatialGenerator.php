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
use SqlSemantics\Platform\MySql\Statement\Type\Keyword\SpatialAlias;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\SpatialKind;
use SqlSemantics\Platform\MySql\Statement\Type\Spatial;
use Tests\Generator\MySql\Statement\Type\Kind\SpatialKindGenerator;

/**
 * Generates Spatial values from the generators of its operands.
 *
 * @implements ErisGenerator<Spatial>
 */
final class SpatialGenerator implements ErisGenerator
{
    /** @var ErisGenerator<Spatial> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of Spatial from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::bind(
            new SpatialKindGenerator($release),
            static function (SpatialKind $kind): ErisGenerator {
                $aliases = array_filter(SpatialAlias::cases(), static fn (SpatialAlias $alias): bool => $alias->kind() === $kind);

                return Generators::map(
                    static fn (?SpatialAlias $alias): Spatial => new Spatial(kind: $kind, inputSource: null, alias: $alias),
                    $aliases === [] ? Generators::constant(null) : Generators::oneOf(Generators::constant(null), Generators::elements($aliases))
                );
            }
        );
        $this->delegate = Generators::suchThat(
            static function (Spatial $value) use ($release): bool {
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
     * @return GeneratedValueSingle<Spatial>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<Spatial>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
