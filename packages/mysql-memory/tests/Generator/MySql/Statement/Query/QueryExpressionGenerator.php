<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes QueryExpression values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\QueryExpression>
 */
final class QueryExpressionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Query\QueryExpression> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\WithClause|null> $with Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Query> $body Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Query\OrderItem|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredOrderItem>>|null $orderBy Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Limit|null>|null $limit Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\QueryFact|null>|null $queryInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $with, ErisGenerator $body, ?ErisGenerator $orderBy = null, ?ErisGenerator $limit = null, ?ErisGenerator $queryInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Query\QueryExpression {
            return new \SqlSemantics\Platform\MySql\Statement\Query\QueryExpression(with: $parts[0], body: $parts[1], orderBy: $parts[2], limit: $parts[3], queryInformation: $parts[4], inputSource: $parts[5]);
        }, \Tests\Generator\Operands::tuple(
            $with,
            $body,
            $orderBy ?? Generators::constant([]),
            $limit ?? Generators::constant(null),
            $queryInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
