<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Projection;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Equivalence;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Shape\Field;

/**
 * Resolves sorting expressions to output fields or additional projected keys.
 *
 * An alias identifies its block and output position even when ROLLUP has rebuilt
 * the field's type and nullability facts. Reading that position preserves a single
 * evaluation of the selected expression. Verified through PDO on MySQL 8.0, 8.4 and 9.1.
 *
 * @visibility MySqlMemory
 */
final class Ordering
{
    /**
     * @param Planner $planner The statement planner
     */
    public function __construct(public readonly Planner $planner)
    {
    }

    /**
     * Answers sorting positions and the projection extended with any additional keys.
     *
     * @param list<Field> $fields The visible output fields
     * @param list<Domain> $domains Their output domains
     * @param list<Evaluable> $expressions Their compiled expressions
     * @return array{list<array{int, Domain, bool}>, list<Evaluable>}
     * @throws SqlError When a sorting expression cannot be compiled
     */
    public function keys(Select $select, array $fields, array $domains, array $expressions, Scope $evaluation): array
    {
        $compiler = $this->planner->compiler;
        $evaluation = $this->evaluation($select, $fields, $domains, $evaluation);
        $keys = [];
        foreach ([...$select->orderBy, ...($select->late === null ? [] : $select->late->orderBy)] as $item) {
            $target = $item->expression;
            while ($target instanceof Grouped) {
                $target = $target->operand;
            }
            $resolution = ($target instanceof ColumnUse || $target instanceof OutputOrdinal) && $compiler->facts->covers($target) ? $compiler->facts->scalar($target)->resolution : null;
            $position = $resolution instanceof AliasTarget && $resolution->depth === 0 ? $resolution->field->position : false;
            if ($position === false && $target instanceof ColumnUse) {
                foreach ($fields as $selected => $field) {
                    if ($field->resolution instanceof ResolvedColumn && (new Equivalence($this->planner))->sameColumn($field->resolution, $target)) {
                        $position = $selected;
                        break;
                    }
                }
            }
            if (is_int($position)) {
                $selected = $fields[$position]->expression;
                if ($selected !== null && $compiler->constancy($selected)->constant() && (new Walker())->find($selected, Query::class) === []) {
                    continue;
                }
                $keys[] = [$position, $domains[$position], $item->direction?->value === 'DESC'];
                continue;
            }
            $key = $compiler->compile($item->expression, $evaluation);
            if ($compiler->constancy($item->expression)->constant() && (new Walker())->find($item->expression, Query::class) === []) {
                continue;
            }
            $keys[] = [count($expressions), $key->domain(), $item->direction?->value === 'DESC'];
            $expressions[] = $key;
        }

        return [$keys, $expressions];
    }

    /**
     * Reuses non-DISTINCT legacy assignment fields when sorting evaluates the list early.
     *
     * Nested aliases of assignments read those fields in MySQL 5.6 and 5.7.
     * A projection deferred until emission still computes its sorting keys over input rows.
     * Verified through SQL and PDO metadata in Testcontainers.
     *
     * @param list<Field> $fields The selected fields
     * @param list<Domain> $domains Their result domains
     */
    public function evaluation(Select $select, array $fields, array $domains, Scope $scope): Scope
    {
        if (!$this->planner->settings->legacy() || in_array(\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::Distinct, $select->options, true) || (new Emission($this->planner))->sortable($select)) {
            return $scope;
        }
        $ordering = clone $scope;
        foreach ($fields as $position => $field) {
            if ($field->expression !== null && (new Walker())->find($field->expression, \SqlSemantics\Platform\MySql\Statement\Variable\VariableAssignment::class, false) !== []) {
                $ordering->bind($field->expression, new \MySqlMemory\Evaluation\Leaf\ProjectedRead($domains[$position], $position));
            }
        }

        return $ordering;
    }
}
