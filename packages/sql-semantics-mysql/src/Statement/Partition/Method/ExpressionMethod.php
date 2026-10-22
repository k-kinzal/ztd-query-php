<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Partition\Method;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * `RANGE (expression)` or `LIST (expression)`: partitioning by the value of an expression compared with the partition values.
 *
 * Mirrors PT_part_type_def_range_expr and PT_part_type_def_list_expr. The
 * expression is derived in the scope of the partitioned table.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/partitioning-range.html,
 * https://dev.mysql.com/doc/refman/8.4/en/partitioning-list.html.
 *
 * @visibility public
 * @example Holding a range partitioning by an expression
 *     $method = new \SqlSemantics\Platform\MySql\Statement\Partition\Method\ExpressionMethod(\SqlSemantics\Platform\MySql\Statement\Partition\Method\PartitionKind::Range, new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse(new \SqlSemantics\Statement\Identifier\Name('id')));
 *     $method->kind // => \SqlSemantics\Platform\MySql\Statement\Partition\Method\PartitionKind::Range
 * @example Excluding session-dependent partition methods
 *     $expression = new \SqlSemantics\Platform\MySql\Statement\Call\KeywordCall(\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User, []);
 *     new \SqlSemantics\Platform\MySql\Statement\Partition\Method\ExpressionMethod(\SqlSemantics\Platform\MySql\Statement\Partition\Method\PartitionKind::Range, $expression) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class ExpressionMethod implements PartitionMethod
{
    use Snapshot;

    /**
     * @param PartitionKind $kind RANGE or LIST
     * @param Scalar $expression The partitioning expression
     */
    public function __construct(public readonly PartitionKind $kind, public readonly Scalar $expression)
    {
        $rules = new \SqlSemantics\Platform\MySql\Rules\TableDefinition\PartitionExpressions();
        Check::input($rules->possible($expression), 'A partition method cannot depend on session state or stored functions.');
        Check::input($rules->condition($expression, \SqlSemantics\Contract\GrammarRelease::MySql847) !== \SqlSemantics\Platform\MySql\Statement\Partition\Problem\OperandCondition::Subquery, 'A partition subquery needs a retained instruction.');
        Check::input((new Precedence())->admits($expression, Precedence::BIT_EXPR), 'A partition expression needs a grouping to keep its place.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives the expression in the scope of the table.
     */
    public function deriveMethod(Derivation $derivation, Environment $scope): void
    {
        (new \SqlSemantics\Platform\MySql\Rules\TableDefinition\PartitionExpressions())->derive($this->expression, $derivation, $scope);
    }

    /**
     * Writes the method.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->kind->value)->symbol('(')->node($this->expression)->symbol(')');
    }
}
