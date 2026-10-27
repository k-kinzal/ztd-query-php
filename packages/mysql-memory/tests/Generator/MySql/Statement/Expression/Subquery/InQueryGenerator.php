<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Expression\Subquery;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes InQuery values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Subquery\InQuery>
 */
final class InQueryGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Expression\Subquery\InQuery> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand> $operand Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Query> $query Generated constructor operand
     * @param ErisGenerator<bool>|null $negated Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\ScalarFact|null>|null $scalarInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $operand, ErisGenerator $query, ?ErisGenerator $negated = null, ?ErisGenerator $scalarInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\InQuery {
            return new \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\InQuery(operand: $parts[0], query: $parts[1], negated: $parts[2], scalarInformation: $parts[3], inputSource: $parts[4]);
        }, \Tests\Generator\Operands::tuple(
            $operand,
            $query,
            $negated ?? Generators::constant(false),
            $scalarInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
