<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Spatial;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Construction\Publication;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Server\Spatial\CreateSpatialReference;
use SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttribute;
use SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttributeKind;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\MySql\Statement\Literal\NumeralGenerator;
use Tests\Support\Analysis;

/**
 * Generates CreateSpatialReference values from the generators of its operands.
 *
 * @implements ErisGenerator<CreateSpatialReference>
 */
final class CreateSpatialReferenceGenerator implements ErisGenerator
{
    /** @var ErisGenerator<CreateSpatialReference> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of CreateSpatialReference from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): CreateSpatialReference => new CreateSpatialReference(orReplace: $parts[0], ifNotExists: !$parts[0] && $parts[1], srid: $parts[2], attributes: $parts[3], statementInformation: $parts[4], inputSource: $parts[5]),
            Generators::tuple(
                Generators::bool(),
                Generators::bool(),
                new NumeralGenerator($release),
                Generators::map(
                    static fn (array $parts): array => [
                            new SpatialAttribute(SpatialAttributeKind::Name, new Text('srs_' . $parts[0])),
                            new SpatialAttribute(SpatialAttributeKind::Definition, new Text('GEOGCS["WGS 84",DATUM["World Geodetic System 1984",SPHEROID["WGS 84",6378137,298.257223563,AUTHORITY["EPSG","7030"]],AUTHORITY["EPSG","6326"]],PRIMEM["Greenwich",0,AUTHORITY["EPSG","8901"]],UNIT["degree",0.017453292519943278,AUTHORITY["EPSG","9122"]],AXIS["Lat",NORTH],AXIS["Lon",EAST],AUTHORITY["EPSG","4326"]]')),
                            ...($parts[1] ? [new SpatialAttribute(SpatialAttributeKind::Organization, new Text('org_' . $parts[0]), new Numeral((string) $parts[2]))] : []),
                            ...($parts[3] ? [new SpatialAttribute(SpatialAttributeKind::Description, new Text('description_' . $parts[0]))] : []),
                        ],
                    Generators::tuple(Generators::choose(0, 999), Generators::bool(), Generators::choose(1, 999999), Generators::bool())
                ),
                Generators::constant(null),
                Generators::constant(null)
            )
        );
        $this->delegate = Generators::map(
            static function (CreateSpatialReference $statement) use ($release): CreateSpatialReference {
                $published = (new Publication())->publish(new AnalysisContext(new LanguageProfile($release), [new Name('pbt')]), $statement);
                Check::invariant($published instanceof CreateSpatialReference, 'Publication keeps the statement type.');

                return $published;
            },
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static fn (CreateSpatialReference $statement): bool => Analysis::accepted($statement),
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static function (CreateSpatialReference $value) use ($release): bool {
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
     * @return GeneratedValueSingle<CreateSpatialReference>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<CreateSpatialReference>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
