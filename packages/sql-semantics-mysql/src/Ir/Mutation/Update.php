<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Mutation;

use SqlSemantics\Platform\MySql\Ir\BooleanLiteral;
use SqlSemantics\Platform\MySql\Ir\From\OccurrenceScope;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerMembership;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerRange;
use SqlSemantics\Platform\MySql\Ir\Predicate\LogicalPredicate;
use SqlSemantics\Platform\MySql\Ir\Predicate\NullTest;
use SqlSemantics\Platform\MySql\Ir\Predicate\Text\TextComparison;
use SqlSemantics\Platform\MySql\Ir\SelectSql;
use SqlSemantics\Platform\MySql\Ir\TableSource;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A complete update of one declared target with ordered scalar assignments.
 *
 * @visibility public
 * @example An update with resolved slots
 *     $semantics = new \SqlSemantics\Platform\MySql\Semantics();
 *     $ir = $semantics->analyze('UPDATE app.t SET a=b', $semantics->context(['CREATE TABLE app.t (a INTEGER, b INTEGER)']));
 *     count($ir->assignments) // => 1
 */
final class Update implements SemanticsIR
{
    use Snapshot;

    /**
     * @var non-empty-list<UpdateAssignment|TextUpdateAssignment> The assignments in written order
     */
    public readonly array $assignments;

    /**
     * Closes every read and write over the resolved target row.
     *
     * @param array<array-key, mixed> $assignments
     */
    public function __construct(
        public readonly TableSource $target,
        array $assignments,
        public readonly BooleanLiteral|IntegerComparison|IntegerMembership|IntegerRange|TextComparison|LogicalPredicate|NullLiteral|NullTest|null $where = null,
        public readonly GrammarRelease $release = GrammarRelease::MySql910,
    ) {
        Check::input($target->kind->isTable(), 'An UPDATE target must be a base table.');
        Check::input(array_is_list($assignments) && $assignments !== [], 'An UPDATE needs a nonempty assignment list.');
        Check::input(count($assignments) <= 1024, 'An UPDATE can have at most 1024 assignments.');
        $scope = new OccurrenceScope();
        $checked = [];
        foreach ($assignments as $assignment) {
            Check::input($assignment instanceof UpdateAssignment || $assignment instanceof TextUpdateAssignment, 'An UPDATE needs a nonempty assignment list.');
            $scope->updateAssignment($assignment, $target);
            $checked[] = $assignment;
        }
        $scope->predicate($where, [$target]);
        $this->assignments = $checked;
    }

    /**
     * Identifies the pinned native language profile.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8mb4/lower-case-table-names=0/sql-mode=empty/native';
    }

    /**
     * Writes the ordered assignments and the optional filter.
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

        return $statement;
    }
}
