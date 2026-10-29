<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Temporal;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes TimestampCall values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Temporal\TimestampCall>
 */
final class TimestampCallGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Call\Temporal\TimestampCall> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Temporal\TimestampOperation> $operation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit> $unit Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar> $first Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar> $second Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\ScalarFact|null>|null $scalarInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $operation, ErisGenerator $unit, ErisGenerator $first, ErisGenerator $second, ?ErisGenerator $scalarInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Call\Temporal\TimestampCall {
            return new \SqlSemantics\Platform\MySql\Statement\Call\Temporal\TimestampCall(operation: $parts[0], unit: $parts[1], first: $parts[2], second: $parts[3], scalarInformation: $parts[4], inputSource: $parts[5]);
        }, Generators::tuple(
            $operation,
            $unit,
            $first,
            $second,
            $scalarInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
