<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Spatial;

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
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttribute;
use SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttributeKind;
use Tests\Generator\MySql\Statement\Literal\NumeralGenerator;
use Tests\Generator\MySql\Statement\Literal\TextGenerator;

/**
 * Generates SpatialAttribute values from the generators of its operands.
 *
 * @implements ErisGenerator<SpatialAttribute>
 */
final class SpatialAttributeGenerator implements ErisGenerator
{
    /** @var ErisGenerator<SpatialAttribute> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): SpatialAttribute => new SpatialAttribute(kind: $parts[0], value: $parts[0] === SpatialAttributeKind::Definition ? new Text('GEOGCS["WGS 84",DATUM["World Geodetic System 1984",SPHEROID["WGS 84",6378137,298.257223563,AUTHORITY["EPSG","7030"]],AUTHORITY["EPSG","6326"]],PRIMEM["Greenwich",0,AUTHORITY["EPSG","8901"]],UNIT["degree",0.017453292519943278,AUTHORITY["EPSG","9122"]],AXIS["Lat",NORTH],AXIS["Lon",EAST],AUTHORITY["EPSG","4326"]]') : $parts[1], identifier: $parts[0] === SpatialAttributeKind::Organization ? $parts[2] ?? new Numeral('1') : null, inputSource: $parts[3]),
            Generators::tuple(
                new SpatialAttributeKindGenerator($release),
                new TextGenerator($release),
                Generators::oneOf(Generators::constant(null), new NumeralGenerator($release)),
                Generators::constant(null)
            )
        );
        $this->delegate = Generators::suchThat(
            static function (SpatialAttribute $value) use ($release): bool {
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
     * @return GeneratedValue<SpatialAttribute>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<SpatialAttribute> $element The generated counterexample
     * @return GeneratedValue<SpatialAttribute>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
