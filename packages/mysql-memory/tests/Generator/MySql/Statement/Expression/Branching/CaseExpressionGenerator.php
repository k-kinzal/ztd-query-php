<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Expression\Branching;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes CaseExpression values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Branching\CaseExpression>
 */
final class CaseExpressionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Expression\Branching\CaseExpression> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|null> $operand Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Expression\Branching\CaseBranch>> $branches Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|null>|null $else Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\ScalarFact|null>|null $scalarInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $operand, ErisGenerator $branches, ?ErisGenerator $else = null, ?ErisGenerator $scalarInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Expression\Branching\CaseExpression {
            return new \SqlSemantics\Platform\MySql\Statement\Expression\Branching\CaseExpression(operand: $parts[0], branches: $parts[1], else: $parts[2], scalarInformation: $parts[3], inputSource: $parts[4]);
        }, Generators::tuple(
            $operand,
            $branches,
            $else ?? Generators::constant(null),
            $scalarInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
