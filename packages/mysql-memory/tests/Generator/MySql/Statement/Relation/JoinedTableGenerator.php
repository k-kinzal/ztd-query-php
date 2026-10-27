<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Relation;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes JoinedTable values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable>
 */
final class JoinedTableGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Relation\TableSource> $left Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Relation\Join\JoinOperator> $operator Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Relation\TableSource> $right Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand|null>|null $on Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Identifier\Name>>|null $using Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\RelationFact|null>|null $relationInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $left, ErisGenerator $operator, ErisGenerator $right, ?ErisGenerator $on = null, ?ErisGenerator $using = null, ?ErisGenerator $relationInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable {
            return new \SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable(left: $parts[0], operator: $parts[1], right: $parts[2], on: $parts[3], using: $parts[4], relationInformation: $parts[5], inputSource: $parts[6]);
        }, \Tests\Generator\Operands::tuple(
            $left,
            $operator,
            $right,
            $on ?? Generators::constant(null),
            $using ?? Generators::constant([]),
            $relationInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
