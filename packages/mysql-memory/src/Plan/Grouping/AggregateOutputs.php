<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Grouping;

use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\ColumnOrigin;
use MySqlMemory\Plan\Grouping;
use MySqlMemory\Plan\Origins;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Result\ColumnFlag;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Platform\MySql\Rules\Query\Grouping\GroupKeys;
use SqlSemantics\Platform\MySql\Rules\Query\Grouping\Matching;
use SqlSemantics\Platform\MySql\Rules\Query\Grouping\RollupItems;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\JsonObjectAggregate;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Statement\Shape\Field;

/**
 * Describes grouped aggregate outputs from their resolved types and physical grouping.
 *
 * Temporary grouping fields differ from streaming expressions. MIN and MAX
 * copy their bound input's missing-default flag; computed parents remain
 * expressions. AVG and variance finalize their grouped state as expressions
 * unless a later stage stores the result. Observed on native MySQL 5.7 and 8.4.
 *
 * @visibility MySqlMemory
 */
final class AggregateOutputs
{
    /**
     * @param Planner $planner The planner with resolved input occurrences
     */
    public function __construct(public readonly Planner $planner)
    {
    }

    /**
     * Answers an aggregate's stored flags, or leaves other origins unchanged.
     */
    public function origin(Select $select, Field $field, Scope $scope, Domain $domain): ?ColumnOrigin
    {
        $call = $field->expression === null ? null : (new Matching())->unwrap($field->expression);
        if (!$call instanceof Aggregate || $call->over !== null || $select->groupBy === null || (new RollupItems())->windowed($select)) {
            return null;
        }
        $varying = (new GroupKeys())->varying($select, $this->planner->compiler->facts);
        $streaming = !$varying || $this->streaming($select, $scope);
        $copied = $this->copied($select);
        if (!$copied && $streaming) {
            return $domain->kind->temporal() ? new ColumnOrigin('', '', '', '', $domain->flags() & ~ColumnFlag::Binary->value, true) : null;
        }
        if (in_array($call->function, [AggregateFunction::Average, AggregateFunction::StandardDeviation, AggregateFunction::Variance, AggregateFunction::SampleStandardDeviation, AggregateFunction::SampleVariance], true) && !$streaming) {
            return null;
        }
        $name = $copied && $streaming && !$this->planner->settings->legacy() ? $this->planner->blocks->name($field, $select) : '';
        if (!$domain->kind->numeric()) {
            return $name === '' ? null : new ColumnOrigin('', '', '', $name);
        }
        $flags = 0;
        if (in_array($call->function, [AggregateFunction::Minimum, AggregateFunction::Maximum], true)) {
            $argument = new Field($field->position, $field->slot, $call->arguments[0]);
            $flags = ((new Origins($this->planner))->origin($argument, $scope)->flags ?? 0) & ColumnFlag::NoDefaultValue->value;
        }

        return new ColumnOrigin('', '', '', $name, $flags);
    }

    /**
     * Retains a streaming aggregate's logical type when no later stage stores it.
     * The semantic field also retains the stored form; index selection is a plan decision.
     */
    public function domain(Select $select, Field $field, Scope $scope, Domain $reported, Domain $logical): Domain
    {
        $call = $field->expression === null ? null : (new Matching())->unwrap($field->expression);
        if (!$call instanceof Aggregate || $call->over !== null || $select->groupBy === null || (new RollupItems())->windowed($select) || $this->copied($select)) {
            return $reported;
        }

        return !(new GroupKeys())->varying($select, $this->planner->compiler->facts) || $this->streaming($select, $scope) ? $logical : $reported;
    }

    /**
     * Identifies a later output copy, distinct from the grouping state itself.
     */
    public function copied(Select $select): bool
    {
        return (new GroupKeys())->varying($select, $this->planner->compiler->facts) && ((new Origins($this->planner))->aggregateOrder($select) || in_array(SelectOption::Distinct, $select->options, true));
    }

    /**
     * Identifies ordered grouping that emits aggregate expressions directly.
     */
    public function streaming(Select $select, Scope $scope): bool
    {
        $grouping = new Grouping($this->planner);
        if ($select->groupBy?->modifier !== null || $grouping->indexed($select, $scope) || in_array(SelectOption::BigResult, $select->options, true)) {
            return true;
        }
        foreach ($grouping->collect($select) as $aggregate) {
            if ($aggregate instanceof GroupConcat || $aggregate instanceof JsonObjectAggregate || $aggregate->function === AggregateFunction::JsonArray || ($aggregate->distinct && !in_array($aggregate->function, [AggregateFunction::Minimum, AggregateFunction::Maximum], true))) {
                return true;
            }
        }

        return false;
    }
}
