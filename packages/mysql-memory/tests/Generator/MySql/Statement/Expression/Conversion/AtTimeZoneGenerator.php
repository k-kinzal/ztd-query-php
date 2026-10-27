<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Expression\Conversion;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes AtTimeZone values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Conversion\AtTimeZone>
 */
final class AtTimeZoneGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Expression\Conversion\AtTimeZone> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar> $operand Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Conversion\TimeZone> $zone Generated constructor operand
     * @param ErisGenerator<bool>|null $interval Generated constructor operand
     * @param ErisGenerator<string|null>|null $precision Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\ScalarFact|null>|null $scalarInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $operand, ErisGenerator $zone, ?ErisGenerator $interval = null, ?ErisGenerator $precision = null, ?ErisGenerator $scalarInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Expression\Conversion\AtTimeZone {
            return new \SqlSemantics\Platform\MySql\Statement\Expression\Conversion\AtTimeZone(operand: $parts[0], zone: $parts[1], interval: $parts[2], precision: $parts[3], scalarInformation: $parts[4], inputSource: $parts[5]);
        }, \Tests\Generator\Operands::tuple(
            $operand,
            $zone,
            $interval ?? Generators::constant(false),
            $precision ?? Generators::constant(null),
            $scalarInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
