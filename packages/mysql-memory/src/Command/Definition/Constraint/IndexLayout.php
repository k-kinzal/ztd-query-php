<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition\Constraint;

use Closure;
use MySqlMemory\Error\Family\ConstraintError;
use MySqlMemory\Evaluation\Compile\Walker;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ArrayPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ExpressionPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;

/**
 * Preserves retained dependencies and creates distinct occurrences for added indexes.
 *
 * Reusing a statement can encounter an index created by that same semantic object.
 * Reconstruct added indexes through semantic constructors before combining them
 * with retained indexes. SQL validation then keeps its ordinary error precedence.
 *
 * @visibility MySqlMemory
 */
final class IndexLayout
{
    /**
     * Refuses a retained expression whose column is dropped or renamed.
     *
     * @param Closure(string): (string|false|null) $renamed The current name of an old column, false when dropped, or null when not previously declared
     * @throws \MySqlMemory\Error\SqlError When an expression loses one of its dependencies
     */
    public function dependencies(ExpressionPart|ArrayPart $part, Closure $renamed): void
    {
        foreach ((new Walker())->find($part->expression, ColumnUse::class) as $use) {
            $name = $renamed($use->name->value);
            if ($name === false || ($name !== null && strcasecmp($name, $use->name->value) !== 0)) {
                throw ConstraintError::FunctionalDependency->error($use->name->value);
            }
        }
    }

    /**
     * Reconstructs an added index without retaining occurrences that an old index may share.
     */
    public function added(IndexDefinition $index): IndexDefinition
    {
        $copy = (new \SqlSemantics\Platform\MySql\Construction\Occurrences())->node($index);
        \SqlSemantics\Diagnostic\Check::invariant($copy instanceof IndexDefinition, 'Index reconstruction preserves its declaration type.');

        return $copy;
    }
}
