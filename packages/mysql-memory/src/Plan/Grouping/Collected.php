<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Grouping;

use MySqlMemory\Evaluation\Aggregate\Accumulation;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Grouping;
use MySqlMemory\Plan\Planner;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\JsonObjectAggregate;

/**
 * Collects aggregate output slots while a deferred projection is prepared in order.
 *
 * Each slot has its real accumulation plan and output domain. A later operand
 * refusal prevents execution of the unfinished projection. Argument preparation
 * uses the input scope and cannot nest another aggregate in this same block.
 *
 * @visibility MySqlMemory
 */
final class Collected
{
    /**
     * @var list<Accumulation> The accumulations in preparation order
     */
    public array $values = [];

    /**
     * @param Planner $planner The statement's compiler and relation plans
     * @param int $offset The input row width preceding aggregate outputs
     */
    public function __construct(public readonly Planner $planner, public readonly int $offset)
    {
    }

    /**
     * Prepares the reached aggregate and binds its output slot for the surrounding expression.
     */
    public function compile(Aggregate|GroupConcat|JsonObjectAggregate $node, Scope $scope): Evaluable
    {
        $input = clone $scope;
        $input->grouping = null;
        $value = (new Grouping($this->planner))->accumulation($node, $input);
        $read = new ColumnRead($value->domain, $this->offset + count($this->values));
        $this->values[] = $value;
        $scope->bind($node, $read);
        return $read;
    }
}
