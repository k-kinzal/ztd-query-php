<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Mutation;

use SqlSemantics\Statement\Expression\Reference\Ownership;
use SqlSemantics\Statement\Expression\ScalarExpression;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\TableReference;
use SqlSemantics\Statement\Schema\Definition\ConflictAction;
use SqlSemantics\Statement\SemanticGraph;

/**
 * SQLite update assignments read the input row before any destination values are changed.
 * Repeated destinations are retained in order; the last assignment supplies the stored value.
 * @visibility public
 * @example Identifying an operation without evaluating its assignments
 *     is_subclass_of(\SqlSemantics\Statement\Mutation\SqliteUpdate::class, \SqlSemantics\Statement\Operation::class) // => true
 */
final class SqliteUpdate implements Operation
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * @var non-empty-list<ColumnAssignment>
     */
    public readonly array $assignments;

    /**
     * Asserts the separate destination and expression relationships against one declaration context.
     */
    public function __construct(public readonly TableReference $target, public readonly Scope $scope, public readonly ?ScalarExpression $where = null, public readonly ConflictAction $conflict = ConflictAction::Implicit, ColumnAssignment ...$assignments)
    {
        \SqlSemantics\Statement\Validation\Check::input(($scope->tables[0] ?? null) === $target, 'The update input begins with its actual target occurrence.');
        \SqlSemantics\Statement\Validation\Check::input($assignments !== [], 'An update contains at least one assignment.');
        $this->assignments = array_values($assignments);
        $expressions = $where === null ? [] : [$where];
        foreach ($assignments as $assignment) {
            \SqlSemantics\Statement\Validation\Check::input($assignment->column->scope->tables === [$target], 'Every destination refers to the updated table occurrence.');
            $expressions[] = $assignment->expression;
        }
        foreach ($expressions as $expression) {
            \SqlSemantics\Statement\Validation\Check::input((new Ownership())->accepts($expression, $scope), 'Assignment inputs and predicates use the update input scope.');
        }
        \SqlSemantics\Statement\Validation\Check::input((new SemanticGraph())->containsOnlyValues($this), 'An update retains only immutable semantic values.');
    }

    /**
     * Returns assignments that supply stored values, in last-occurrence order, without changing name validation.
     * @return non-empty-list<ColumnAssignment>
     */
    public function effectiveAssignments(): array
    {
        $effective = [];
        foreach ($this->assignments as $assignment) {
            foreach ($effective as $position => $previous) {
                if ($this->scope->catalog->columnNames->equal($previous->column->name->value, $assignment->column->name->value)) {
                    unset($effective[$position]);
                }
            }
            $effective[] = $assignment;
        }
        return array_values($effective);
    }

    /**
     * Reconstructs the simultaneous update, including its independent FROM relation occurrences.
     */
    public function toString(): string
    {
        $sql = 'UPDATE' . ($this->conflict === ConflictAction::Implicit ? '' : ' OR ' . $this->conflict->value) . ' ' . $this->target->toString() . ' SET ' . implode(', ', array_map(static fn (ColumnAssignment $assignment): string => $assignment->toString(), $this->assignments));
        $inputs = array_slice($this->scope->tables, 1);
        $sql .= $inputs === [] ? '' : ' FROM ' . implode(', ', array_map(static fn (TableReference $table): string => $table->toString(), $inputs));
        return $sql . ($this->where === null ? '' : ' WHERE ' . $this->where->toString());
    }
}
