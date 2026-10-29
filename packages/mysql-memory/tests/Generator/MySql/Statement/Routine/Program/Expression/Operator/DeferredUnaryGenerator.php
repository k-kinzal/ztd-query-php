<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Expression\Operator;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DeferredUnary values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Operator\DeferredUnary>
 */
final class DeferredUnaryGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Operator\DeferredUnary> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator> $operator Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand|\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand> $operand Generated constructor operand
     * @param ErisGenerator<bool>|null $keyword Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\ScalarFact|null>|null $scalarInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $operator, ErisGenerator $operand, ?ErisGenerator $keyword = null, ?ErisGenerator $scalarInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Operator\DeferredUnary {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Operator\DeferredUnary(operator: $parts[0], operand: $parts[1], keyword: $parts[2], scalarInformation: $parts[3], inputSource: $parts[4]);
        }, Generators::tuple(
            $operator,
            $operand,
            $keyword ?? Generators::constant(false),
            $scalarInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
