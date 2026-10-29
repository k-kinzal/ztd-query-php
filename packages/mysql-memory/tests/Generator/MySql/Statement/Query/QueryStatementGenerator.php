<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes QueryStatement values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\QueryStatement>
 */
final class QueryStatementGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Query\QueryStatement> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Query> $query Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Query\Locking\LockingClause>>|null $locking Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Into\IntoDestination|null>|null $into Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Into\IntoPosition|null>|null $intoPosition Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\QueryFact|null>|null $queryInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $query, ?ErisGenerator $locking = null, ?ErisGenerator $into = null, ?ErisGenerator $intoPosition = null, ?ErisGenerator $queryInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Query\QueryStatement {
            return new \SqlSemantics\Platform\MySql\Statement\Query\QueryStatement(query: $parts[0], locking: $parts[1], into: $parts[2], intoPosition: $parts[3], queryInformation: $parts[4], inputSource: $parts[5]);
        }, Generators::tuple(
            $query,
            $locking ?? Generators::constant([]),
            $into ?? Generators::constant(null),
            $intoPosition ?? Generators::constant(null),
            $queryInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
