<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Mutation;

use SqlSemantics\Platform\MySql\Ir\BooleanLiteral;
use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\From\OccurrenceScope;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\MySql\Ir\Predicate\LogicalPredicate;
use SqlSemantics\Platform\MySql\Ir\Predicate\NullTest;
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
     * @var non-empty-list<UpdateAssignment> The assignments in written order
     */
    public readonly array $assignments;

    /**
     * Closes every read and write over the resolved target row.
     *
     * @param list<UpdateAssignment> $assignments
     */
    public function __construct(
        public readonly TableSource $target,
        array $assignments,
        public readonly BooleanLiteral|IntegerComparison|LogicalPredicate|NullLiteral|NullTest|null $where = null,
        public readonly GrammarRelease $release = GrammarRelease::MySql910,
    ) {
        $this->assignments = Check::listOf($assignments, UpdateAssignment::class, 'An UPDATE needs a nonempty assignment list.', 1);
        Check::input(count($this->assignments) <= 1024, 'An UPDATE can have at most 1024 assignments.');
        foreach ($this->assignments as $assignment) {
            $slot = $assignment->target->slot;
            Check::input(isset($target->columns[$slot]), 'An UPDATE write slot must belong to the target.');
            $value = $assignment->value;
            Check::input(!$value instanceof ColumnReference || isset($target->columns[$value->slot]), 'An UPDATE read slot must belong to the target.');
        }
        (new OccurrenceScope())->predicate($where, [$target]);
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
