<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Spatial;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes CreateSpatialReference values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Spatial\CreateSpatialReference>
 */
final class CreateSpatialReferenceGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Spatial\CreateSpatialReference> */
    use Composition;

    /**
     * @param ErisGenerator<bool> $orReplace Generated constructor operand
     * @param ErisGenerator<bool> $ifNotExists Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Numeral> $srid Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttribute>> $attributes Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $orReplace, ErisGenerator $ifNotExists, ErisGenerator $srid, ErisGenerator $attributes, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Spatial\CreateSpatialReference {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Spatial\CreateSpatialReference(orReplace: $parts[0], ifNotExists: $parts[1], srid: $parts[2], attributes: $parts[3], statementInformation: $parts[4], inputSource: $parts[5]);
        }, \Tests\Generator\Operands::tuple(
            $orReplace,
            $ifNotExists,
            $srid,
            $attributes,
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Spatial\CreateSpatialReference>>
     */
    public static function forms(): iterable
    {
        yield 'CreateSpatialReference/26' => new CreateSpatialReferenceGenerator(
            orReplace: Generators::constant(true),
            ifNotExists: Generators::constant(false),
            srid: new \Tests\Generator\MySql\Statement\Literal\NumeralGenerator(
                text: Generators::constant('1'),
                hexadecimal: Generators::constant(false)
            ),
            attributes: Generators::constant([])
        );
        yield 'CreateSpatialReference/144' => new CreateSpatialReferenceGenerator(
            orReplace: Generators::constant(true),
            ifNotExists: Generators::constant(false),
            srid: new \Tests\Generator\MySql\Statement\Literal\NumeralGenerator(
                text: Generators::constant('1'),
                hexadecimal: Generators::constant(false)
            ),
            attributes: \Tests\Generator\Operands::tuple(
                new SpatialAttributeGenerator(
                    kind: SpatialAttributeKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttributeKind::Name]),
                    value: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                        value: Generators::constant('text'),
                        radix: Generators::constant(null)
                    ),
                    identifier: Generators::constant(null)
                )
            )
        );
    }

}
