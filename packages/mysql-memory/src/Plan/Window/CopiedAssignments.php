<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Window;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Leaf\Assignment;
use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Evaluation\Leaf\Retyped;
use MySqlMemory\Plan\Path\AccessPath;
use MySqlMemory\Plan\Path\Transform\Project;
use MySqlMemory\Plan\Planner;
use SqlSemantics\Platform\MySql\Rules\Query\Aggregation;
use SqlSemantics\Platform\MySql\Rules\Query\Grouping\Matching;
use SqlSemantics\Platform\MySql\Rules\Query\Result\OutputFields;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableAssignment;
use SqlSemantics\Statement\Shape\Field;

/**
 * Publishes window assignments when their stored output fields are read.
 *
 * Evaluating a temporal operand initially stores a latin1 string. Reading its copied
 * assignment field uses the field's character set instead. A sorting alias reads that
 * field before LIMIT; ordinary output reads it after LIMIT. Aggregate parents remain
 * expressions unless DISTINCT stores them. Verified through PDO on MySQL 8.0, 8.4 and 9.1.
 *
 * @visibility MySqlMemory
 */
final class CopiedAssignments
{
    /**
     * @param Planner $planner The statement planner
     */
    public function __construct(public readonly Planner $planner)
    {
    }

    /**
     * Identifies bare assignment outputs copied into window result fields.
     *
     * @param list<Field> $fields The visible output fields
     * @return array<int, true> The positions of copied assignments
     */
    public function copies(Select $select, array $fields, bool $materialized): array
    {
        if (!$materialized) {
            return [];
        }
        $positions = [];
        foreach ($fields as $position => $field) {
            $expression = $field->expression;
            if ($expression === null || !(new Matching())->unwrap($expression) instanceof VariableAssignment || !(new Windowing($this->planner))->reads($expression)) {
                continue;
            }
            if (!(new Aggregation())->aggregates([$expression]) || (in_array(SelectOption::Distinct, $select->options, true) && !(new OutputFields())->deferred($select, $expression))) {
                $positions[$position] = true;
            }
        }

        return $positions;
    }

    /**
     * Completes DISTINCT storage before copied fields are read for sorting or output.
     *
     * @param array<int, true> $positions The copied assignment positions
     */
    public function buffered(Select $select, AccessPath $input, array $positions): AccessPath
    {
        return $positions !== [] && in_array(SelectOption::Distinct, $select->options, true) ? new \MySqlMemory\Plan\Path\Transform\Buffer($input) : $input;
    }

    /**
     * Reads copied assignment fields in sorting-key order without evaluating operands again.
     * Their effects occupy hidden projection slots, leaving the visible positions unchanged.
     *
     * @param list<Evaluable> $expressions The projection, including ordering values
     * @param array<int, true> $positions The copied assignment positions in sorting-key order
     */
    public function sorted(AccessPath $input, array $expressions, array $positions): AccessPath
    {
        if ($positions === []) {
            return $input;
        }
        $values = [];
        foreach (array_keys($positions) as $position) {
            $expression = $expressions[$position];
            $read = new ColumnRead($expression->domain(), $position);
            while ($expression instanceof Retyped) {
                $expression = $expression->evaluable;
            }
            if ($expression instanceof Assignment) {
                $values[] = new Assignment($expression->name, $read, $this->planner->compiler->names->stored($read->domain()), $read->domain());
            }
        }

        return $values === [] ? $input : new Project($input, $values, true);
    }
}
