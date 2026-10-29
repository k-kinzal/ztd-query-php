<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query\Set;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes SetOperation values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation>
 */
final class SetOperationGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Query|\SqlSemantics\Platform\MySql\Statement\Query\Set\LeadingUnion> $left Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator> $operator Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Set\SetQuantifier|null> $quantifier Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Query> $right Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\QueryFact|null>|null $queryInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $left, ErisGenerator $operator, ErisGenerator $quantifier, ErisGenerator $right, ?ErisGenerator $queryInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation {
            return new \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation(left: $parts[0], operator: $parts[1], quantifier: $parts[2], right: $parts[3], queryInformation: $parts[4], inputSource: $parts[5]);
        }, Generators::tuple(
            $left,
            $operator,
            $quantifier,
            $right,
            $queryInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
