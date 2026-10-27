<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query\Set;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes OrderedSetOperation values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Set\OrderedSetOperation>
 */
final class OrderedSetOperationGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Query\Set\OrderedSetOperation> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Query|\SqlSemantics\Platform\MySql\Statement\Query\Set\LeadingUnion> $left Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator> $operator Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Set\SetQuantifier|null> $quantifier Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Select|\SqlSemantics\Platform\MySql\Statement\Query\With\DeferredBody> $right Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Query\OrderItem|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredOrderItem>>|null $orderBy Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Limit|null>|null $limit Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\QueryFact|null>|null $queryInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $left, ErisGenerator $operator, ErisGenerator $quantifier, ErisGenerator $right, ?ErisGenerator $orderBy = null, ?ErisGenerator $limit = null, ?ErisGenerator $queryInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Query\Set\OrderedSetOperation {
            return new \SqlSemantics\Platform\MySql\Statement\Query\Set\OrderedSetOperation(left: $parts[0], operator: $parts[1], quantifier: $parts[2], right: $parts[3], orderBy: $parts[4], limit: $parts[5], queryInformation: $parts[6], inputSource: $parts[7]);
        }, \Tests\Generator\Operands::tuple(
            $left,
            $operator,
            $quantifier,
            $right,
            $orderBy ?? Generators::constant([]),
            $limit ?? Generators::constant(null),
            $queryInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
