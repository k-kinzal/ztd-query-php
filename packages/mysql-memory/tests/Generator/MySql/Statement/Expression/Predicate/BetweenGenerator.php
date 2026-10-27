<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Expression\Predicate;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Between values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Predicate\Between>
 */
final class BetweenGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Expression\Predicate\Between> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar> $operand Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar> $low Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar> $high Generated constructor operand
     * @param ErisGenerator<bool>|null $negated Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\ScalarFact|null>|null $scalarInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $operand, ErisGenerator $low, ErisGenerator $high, ?ErisGenerator $negated = null, ?ErisGenerator $scalarInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Expression\Predicate\Between {
            return new \SqlSemantics\Platform\MySql\Statement\Expression\Predicate\Between(operand: $parts[0], low: $parts[1], high: $parts[2], negated: $parts[3], scalarInformation: $parts[4], inputSource: $parts[5]);
        }, \Tests\Generator\Operands::tuple(
            $operand,
            $low,
            $high,
            $negated ?? Generators::constant(false),
            $scalarInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
