<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Select values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Select>
 */
final class SelectGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Query\Select> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Query\SelectOption>> $options Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Node>> $items Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Relation\TableSource|\SqlSemantics\Platform\MySql\Statement\Relation\Dual|null>|null $from Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|null>|null $where Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Clause\Grouping|null>|null $groupBy Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|null>|null $having Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Query\Clause\WindowDefinition>>|null $windows Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|null>|null $qualify Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Query\OrderItem|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredOrderItem>>|null $orderBy Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Limit|null>|null $limit Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Clause\ProcedureAnalyse|null>|null $procedure Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Query\Locking\LockingClause>>|null $locking Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Into\IntoDestination|null>|null $into Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Into\IntoPosition|null>|null $intoPosition Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Clause\LateOrdering|null>|null $late Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Hint\OptimizerHint>>|null $hints Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\QueryFact|null>|null $queryInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $options, ErisGenerator $items, ?ErisGenerator $from = null, ?ErisGenerator $where = null, ?ErisGenerator $groupBy = null, ?ErisGenerator $having = null, ?ErisGenerator $windows = null, ?ErisGenerator $qualify = null, ?ErisGenerator $orderBy = null, ?ErisGenerator $limit = null, ?ErisGenerator $procedure = null, ?ErisGenerator $locking = null, ?ErisGenerator $into = null, ?ErisGenerator $intoPosition = null, ?ErisGenerator $late = null, ?ErisGenerator $hints = null, ?ErisGenerator $queryInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = new \Tests\Generator\Semantics\Statement\SelectionGenerator([Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Query\Select {
            return new \SqlSemantics\Platform\MySql\Statement\Query\Select(options: $parts[0], items: $parts[1], from: $parts[2], where: $parts[3], groupBy: $parts[4], having: $parts[5], windows: $parts[6], qualify: $parts[7], orderBy: $parts[8], limit: $parts[9], procedure: $parts[10], locking: $parts[11], into: $parts[12], intoPosition: $parts[13], late: $parts[14], hints: $parts[15], queryInformation: $parts[16], inputSource: $parts[17]);
        }, \Tests\Generator\Operands::tuple(
            $options,
            $items,
            $from ?? Generators::constant(null),
            $where ?? Generators::constant(null),
            $groupBy ?? Generators::constant(null),
            $having ?? Generators::constant(null),
            $windows ?? Generators::constant([]),
            $qualify ?? Generators::constant(null),
            $orderBy ?? Generators::constant([]),
            $limit ?? Generators::constant(null),
            $procedure ?? Generators::constant(null),
            $locking ?? Generators::constant([]),
            $into ?? Generators::constant(null),
            $intoPosition ?? Generators::constant(null),
            $late ?? Generators::constant(null),
            $hints ?? Generators::constant([]),
            $queryInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ))]);
    }
}
