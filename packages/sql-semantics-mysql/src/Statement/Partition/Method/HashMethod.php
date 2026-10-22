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
 * `[LINEAR] HASH (expression)`: partitioning by the value of an expression modulo the number of partitions.
 *
 * Mirrors PT_part_type_def_hash and PT_sub_partition_by_hash. The
 * expression is derived in the scope of the partitioned table.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/partitioning-hash.html.
 *
 * @visibility public
 * @example Holding a hash partitioning
 *     $method = new \SqlSemantics\Platform\MySql\Statement\Partition\Method\HashMethod(false, new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse(new \SqlSemantics\Statement\Identifier\Name('id')));
 *     [$method->linear, $method->expression->name->value] // => [false, 'id']
 * @example Excluding session-dependent partition methods
 *     $expression = new \SqlSemantics\Platform\MySql\Statement\Call\KeywordCall(\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User, []);
 *     new \SqlSemantics\Platform\MySql\Statement\Partition\Method\HashMethod(false, $expression) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class HashMethod implements PartitionMethod
{
    use Snapshot;

    /**
     * @param bool $linear Whether LINEAR is written
     * @param Scalar $expression The partitioning expression
     */
    public function __construct(public readonly bool $linear, public readonly Scalar $expression)
    {
        $rules = new \SqlSemantics\Platform\MySql\Rules\TableDefinition\PartitionExpressions();
        Check::input($rules->possible($expression), 'A partition method cannot depend on session state or stored functions.');
        Check::input($rules->condition($expression, \SqlSemantics\Contract\GrammarRelease::MySql847) !== \SqlSemantics\Platform\MySql\Statement\Partition\Problem\OperandCondition::Subquery, 'A partition subquery needs a retained instruction.');
        Check::input((new Precedence())->admits($expression, Precedence::BIT_EXPR), 'A partition expression needs a grouping to keep its place.');
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
        if ($this->linear) {
            $out->keyword('LINEAR');
        }
        $out->keyword('HASH')->symbol('(')->node($this->expression)->symbol(')');
    }
}
