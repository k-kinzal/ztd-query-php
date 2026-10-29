<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ExplicitTable values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable>
 */
final class ExplicitTableGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName> $table Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\RelationFact|null>|null $relationInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\QueryFact|null>|null $queryInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $table, ?ErisGenerator $relationInformation = null, ?ErisGenerator $queryInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable {
            return new \SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable(table: $parts[0], relationInformation: $parts[1], queryInformation: $parts[2], inputSource: $parts[3]);
        }, Generators::tuple(
            $table,
            $relationInformation ?? Generators::constant(null),
            $queryInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
