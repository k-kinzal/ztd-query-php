<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Mutation;

use SqlSemantics\Platform\Sqlite\Ir\Arithmetic;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CaseExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CastExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CollatedExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\Concatenation;
use SqlSemantics\Platform\Sqlite\Ir\Expression\Nulls\Coalesce;
use SqlSemantics\Platform\Sqlite\Ir\Expression\UnaryExpression;
use SqlSemantics\Platform\Sqlite\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\BlobLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\HexIntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\RealLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Parameter;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\BetweenTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Comparison;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\ExpressionScope;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\FoldedMembership;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Logical;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\MembershipTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Pattern\PatternTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\TruthTest;
use SqlSemantics\Platform\Sqlite\Ir\SelectSql;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A complete UPDATE over a single resolved base table.
 *
 * @visibility public
 * @example Preparing a checked UPDATE operation
 *     $sqlite = new \SqlSemantics\Platform\Sqlite\Semantics();
 *     $catalog = $sqlite->context(['CREATE TABLE items (id INTEGER)']);
 *     $sqlite->analyze('UPDATE items SET id = 2', $catalog) instanceof \SqlSemantics\Platform\Sqlite\Ir\Mutation\Update // => true
 */
final class Update implements SemanticsIR
{
    use Snapshot;

    /**
     * @var non-empty-list<UpdateAssignment> Assignments in parameter registration order
     */
    public readonly array $assignments;

    /**
     * @var list<ReturnedColumn> The ordered fields of the directly updated rows
     */
    public readonly array $returning;

    /**
     * Checks every write and read against the target occurrence.
     *
     * @param TableSource $target The absolute table and the named read or write slots
     * @param list<UpdateAssignment> $assignments The explicitly writable slots and their values
     * @param list<ReturnedColumn> $returning The ordered fields of the updated row
     */
    public function __construct(
        public readonly TableSource $target,
        array $assignments,
        public readonly Arithmetic|BetweenTest|BlobLiteral|CaseExpression|CastExpression|Coalesce|CollatedExpression|ColumnReference|Comparison|Concatenation|FoldedMembership|HexIntegerLiteral|IntegerLiteral|Logical|MembershipTest|NullLiteral|Parameter|PatternTest|RealLiteral|TextLiteral|TruthTest|UnaryExpression|null $where = null,
        public readonly UpdateConflict $conflict = UpdateConflict::Inherit,
        array $returning = [],
    ) {
        Check::input($target->kind === ExternalRelationKind::BaseTable, 'A mutation target must be a base table.');
        $checked = Check::listOf($assignments, UpdateAssignment::class, 'An UPDATE needs a nonempty ordered assignment list.', 1);
        Check::input(count($checked) <= 2000, 'An UPDATE can have at most 2000 assignments in this profile.');
        $pending = $where === null ? [] : [[$where, 0, 1]];
        foreach ($checked as $assignment) {
            Check::input(isset($target->columns[$assignment->targetSlot]), 'An assignment slot must belong to the UPDATE target.');
            $pending[] = [$assignment->value, 0, 1];
        }
        (new ExpressionScope())->check([count($target->columns)], $pending);
        $output = Check::listOf($returning, ReturnedColumn::class, 'An UPDATE needs checked RETURNING fields.');
        Check::input(count($output) <= 2000, 'An UPDATE can return at most 2000 fields in this profile.');
        foreach ($output as $column) {
            Check::input(isset($target->columns[$column->reference->slot]), 'A returned slot must belong to the UPDATE target.');
        }
        $this->assignments = $checked;
        $this->returning = $output;
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
            UpdateConflict::Fail => ' OR FAIL',
            UpdateConflict::Rollback => ' OR ROLLBACK',
            UpdateConflict::Replace => ' OR REPLACE',
        };
        $assignments = [];
        foreach ($this->assignments as $assignment) {
            $column = $this->target->columns[$assignment->targetSlot]->quoted();
            $assignments[] = $column . ' = ' . $renderer->expression($assignment->value, $this->target);
        }
        $where = $this->where === null ? '' : ' WHERE ' . $renderer->expression($this->where, $this->target);
        $returning = (new ReturningSql())->clause($this->target, $this->returning);

        return 'UPDATE' . $conflict . ' ' . $target . ' SET ' . implode(', ', $assignments) . $where . $returning;
    }
}
