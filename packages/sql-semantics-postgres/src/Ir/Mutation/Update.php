<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Mutation;

use SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\From\BooleanColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\PostgreSql\Ir\From\OccurrenceScope;
use SqlSemantics\Platform\PostgreSql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\From\WideIntegerColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerDistinctness;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerMembership;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerRange;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\LogicalPredicate;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\NullTest;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\Text\TextComparison;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\TruthTest;
use SqlSemantics\Platform\PostgreSql\Ir\SelectSql;
use SqlSemantics\Platform\PostgreSql\Ir\TableSource;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A complete update of one declared target with ordered scalar assignments.
 *
 * @visibility public
 * @example An update with resolved slots
 *     $semantics = new \SqlSemantics\Platform\PostgreSql\Semantics();
 *     $ir = $semantics->analyze('UPDATE public.t SET a=b', $semantics->context(['CREATE TABLE public.t (a INTEGER, b INTEGER)']));
 *     count($ir->assignments) // => 1
 */
final class Update implements SemanticsIR
{
    use Snapshot;

    /**
     * @var non-empty-list<UpdateAssignment> The assignments in written order
     */
    public readonly array $assignments;

    /**
     * @var list<ReturnedColumn> The names and slots read from the updated row
     */
    public readonly array $returning;

    /**
     * Closes every read and write over the resolved target row.
     *
     * @param list<UpdateAssignment> $assignments
     * @param list<ReturnedColumn> $returning Output positions in order
     */
    public function __construct(
        public readonly TableSource $target,
        array $assignments,
        public readonly BooleanColumnReference|BooleanLiteral|IntegerComparison|IntegerDistinctness|IntegerMembership|IntegerRange|LogicalPredicate|NullLiteral|NullTest|TextComparison|TruthTest|null $where = null,
        public readonly GrammarRelease $release = GrammarRelease::PostgreSql172,
        array $returning = [],
    ) {
        Check::input($target->kind === ExternalRelationKind::BaseTable, 'An UPDATE target must be a base table.');
        $this->assignments = Check::listOf($assignments, UpdateAssignment::class, 'An UPDATE needs a nonempty assignment list.', 1);
        Check::input(count($this->assignments) <= 1024, 'An UPDATE can have at most 1024 assignments.');
        $written = [];
        $scope = new OccurrenceScope();
        foreach ($this->assignments as $assignment) {
            $slot = $assignment->target->slot;
            $scope->column($assignment->target, [$target], 'An UPDATE write slot must belong to the target.');
            $value = $assignment->value;
            if ($value instanceof ColumnReference || $value instanceof TextColumnReference
                || $value instanceof BooleanColumnReference || $value instanceof WideIntegerColumnReference) {
                $scope->column($value, [$target], 'An UPDATE read slot must belong to the target.');
            }
            Check::input(!isset($written[$slot]), 'A PostgreSQL UPDATE cannot repeat a target slot.');
            $written[$slot] = true;
        }
        $scope->predicate($where, [$target]);
        $checked = Check::listOf($returning, ReturnedColumn::class, 'An UPDATE needs checked RETURNING fields.');
        (new ReturningLayout())->check($target, $checked);
        $this->returning = $checked;
    }

    /**
     * Identifies the pinned native language profile.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8/standard-conforming-strings/native';
    }

    /**
     * Writes the assignments, optional filter and output layout.
     */
    public function toString(): string
    {
        $sql = new SelectSql();
        $parts = [];
        foreach ($this->assignments as $assignment) {
            $column = $this->target->columns[$assignment->target->slot]->quoted();
            $parts[] = $column . ' = ' . $sql->expression($assignment->value, $this->target);
        }
        $statement = 'UPDATE ' . $sql->from($this->target) . ' SET ' . implode(', ', $parts);
        if ($this->where !== null) {
            $statement .= ' WHERE ' . $sql->predicate($this->where, $this->target);
        }

        return $statement . (new ReturningSql())->clause($this->target, $this->returning);
    }
}
