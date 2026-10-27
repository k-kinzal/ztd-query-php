<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Spatial;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DropSpatialReference values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Spatial\DropSpatialReference>
 */
final class DropSpatialReferenceGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Spatial\DropSpatialReference> */
    use Composition;

    /**
     * @param ErisGenerator<bool> $ifExists Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Numeral> $srid Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $ifExists, ErisGenerator $srid, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Spatial\DropSpatialReference {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Spatial\DropSpatialReference(ifExists: $parts[0], srid: $parts[1], statementInformation: $parts[2], inputSource: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $ifExists,
            $srid,
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Spatial\DropSpatialReference>>
     */
    public static function forms(): iterable
    {
        yield 'DropSpatialReference/38' => new DropSpatialReferenceGenerator(
            ifExists: Generators::constant(false),
            srid: new \Tests\Generator\MySql\Statement\Literal\NumeralGenerator(
                text: Generators::constant('1'),
                hexadecimal: Generators::constant(false)
            )
        );
    }

}
