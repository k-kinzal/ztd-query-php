<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Mutation;

use SqlSemantics\Platform\Sqlite\Ir\Arithmetic;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\BlobLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\HexIntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\RealLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Parameter;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Comparison;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\ExpressionScope;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Logical;
use SqlSemantics\Platform\Sqlite\Ir\SelectSql;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A complete UPDATE over a single resolved base table.
 *
 * @visibility public
 * @example Reading a local read and write slot
 *     $semantics = new \SqlSemantics\Platform\Sqlite\Semantics();
 *     $table = $semantics->inspect('CREATE TABLE entries (votes INTEGER)');
 *     $update = $semantics->analyze('UPDATE entries SET votes = entries.votes + 1', [$table]);
 *     $update->assignments[0]->targetSlot === $update->assignments[0]->value->left->slot // => true
 */
final class Update implements SemanticsIR
{
    use Snapshot;

    /**
     * @var non-empty-list<UpdateAssignment> Assignments in parameter registration order
     */
    public readonly array $assignments;

    /**
     * Checks every write and read against the target occurrence.
     *
     * @param TableSource $target The absolute table and the named read or write slots
     * @param list<UpdateAssignment> $assignments The explicitly writable slots and their values
     */
    public function __construct(
        public readonly TableSource $target,
        array $assignments,
        public readonly Arithmetic|BlobLiteral|ColumnReference|Comparison|HexIntegerLiteral|IntegerLiteral|Logical|NullLiteral|Parameter|RealLiteral|TextLiteral|null $where = null,
        public readonly UpdateConflict $conflict = UpdateConflict::Inherit,
    ) {
        $checked = Check::listOf($assignments, UpdateAssignment::class, 'An UPDATE needs a nonempty ordered assignment list.', 1);
        Check::input(count($checked) <= 2000, 'An UPDATE can have at most 2000 assignments in this profile.');
        $pending = $where === null ? [] : [[$where, 0, 1]];
        foreach ($checked as $assignment) {
            Check::input(isset($target->columns[$assignment->targetSlot]), 'An assignment slot must belong to the UPDATE target.');
            $pending[] = [$assignment->value, 0, 1];
        }
        (new ExpressionScope())->check([$target], $pending);
        $this->assignments = $checked;
    }

    /**
     * Identifies the fixed SQLite language profile.
     */
    public function profileId(): string
    {
        return 'sqlite-3.47.2/utf8/short-column-names/numbered-parameters';
    }

    /**
     * Renders the assignments in lexical order, including overwritten operands.
     */
    public function toString(): string
    {
        $renderer = new SelectSql();
        $target = $renderer->source($this->target, 0);
        $conflict = match ($this->conflict) {
            UpdateConflict::Inherit => '',
            UpdateConflict::Abort => ' OR ABORT',
            UpdateConflict::Ignore => ' OR IGNORE',
        };
        $assignments = [];
        foreach ($this->assignments as $assignment) {
            $column = $this->target->columns[$assignment->targetSlot]->quoted();
            $assignments[] = $column . ' = ' . $renderer->expression($assignment->value, $this->target);
        }
        $where = $this->where === null ? '' : ' WHERE ' . $renderer->expression($this->where, $this->target);

        return 'UPDATE' . $conflict . ' ' . $target . ' SET ' . implode(', ', $assignments) . $where;
    }
}
