<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Problem\Update;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Rules\Dml\ChangeFacts;
use SqlSemantics\Platform\MySql\Statement\Dml\Assignment;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Hint\Comment\HintComment;
use SqlSemantics\Platform\MySql\Statement\Hint\OptimizerHint;
use SqlSemantics\Platform\MySql\Statement\Query\Limit;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Platform\MySql\Statement\Query\WithClause;
use SqlSemantics\Platform\MySql\Statement\Relation\TableSource as Relation;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredOrderItem;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredColumnAssignment;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * The complete input of an UPDATE with an intrinsically conflicting table namespace.
 *
 * This diagnostic is neither an ordinary statement nor a stored instruction.
 * MySQL rejects the collision when defining a routine, including an unreached
 * branch. Retained sources still determine earlier database conditions.
 *
 * @visibility public
 * @example Refusing an alias collision before publishing a routine
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p() UPDATE t,t SET a=1') // throws \SqlSemantics\Construction\RejectedInput
 */
final class ConflictingUpdate implements \SqlSemantics\Statement\Fact\Diagnostic, \SqlSemantics\Statement\Node
{
    use Snapshot;

    /**
     * @var list<OptimizerHint> The hints of the comment written right after the verb, in written order
     */
    public readonly array $hints;

    /**
     * @var non-empty-list<Relation> The table references in written order
     */
    public readonly array $tables;

    /**
     * @var non-empty-list<Assignment|DeferredColumnAssignment> The assignments in written order
     */
    public readonly array $assignments;

    /**
     * @var list<OrderItem|DeferredOrderItem> The ORDER BY items in written order
     */
    public readonly array $orderBy;

    /**
     * @param WithClause|null $with The common tables (MySQL 8.0 and later)
     * @param bool $lowPriority Whether LOW_PRIORITY is written
     * @param bool $ignore Whether IGNORE turns errors into warnings
     * @param list<Relation> $tables The table references; at least one
     * @param list<Assignment|DeferredColumnAssignment> $assignments The assignments; at least one
     * @param Scalar|RowOperand|DeferredOperand|null $where The row predicate
     * @param list<OrderItem|DeferredOrderItem> $orderBy The order the rows are updated in
     * @param Limit|null $limit The most rows updated
     * @param list<OptimizerHint> $hints The hints of the comment written right after the verb, in written order
     * @throws InvalidConstruction When a required operand is absent or the statement role contradicts its operands
     */
    public function __construct(
        public readonly ?WithClause $with,
        public readonly bool $lowPriority,
        public readonly bool $ignore,
        array $tables,
        array $assignments,
        public readonly Scalar|RowOperand|DeferredOperand|null $where = null,
        array $orderBy = [],
        public readonly ?Limit $limit = null,
        array $hints = [],
    ) {
        $this->hints = Check::listOf($hints, OptimizerHint::class, 'The hints of a statement are optimizer hints.');
        $members = [];
        foreach (Check::listOf($tables, Relation::class, 'UPDATE names at least one table reference.', 1) as $table) {
            array_push($members, ...($table instanceof \SqlSemantics\Platform\MySql\Statement\Relation\TableList ? $table->members : [$table]));
        }
        $this->tables = $members;
        $items = [];
        foreach (Check::listOf($assignments, \SqlSemantics\Statement\Node::class, 'UPDATE holds at least one assignment.', 1) as $item) {
            Check::input($item instanceof Assignment || $item instanceof DeferredColumnAssignment, 'UPDATE retains assignment roles.');
            $items[] = $item;
        }
        $this->assignments = $items;
        $ordering = [];
        foreach (Check::listOf($orderBy, \SqlSemantics\Statement\Node::class, 'ORDER BY holds ordering items.') as $item) {
            Check::input($item instanceof OrderItem || $item instanceof DeferredOrderItem, 'UPDATE retains ordering roles.');
            Check::input($item instanceof DeferredOrderItem || $item->expression instanceof Scalar, 'UPDATE ordering uses expressions, not output-column positions.');
            $ordering[] = $item;
        }
        $this->orderBy = $ordering;
        Check::input(!$limit instanceof \SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit || $limit->offset === null, 'UPDATE limits rows without an offset.');
        Check::input((new \SqlSemantics\Platform\MySql\Rules\Dml\Update\Structure())->names($this->tables) !== null, 'A conflicting UPDATE retains an exact repeated input name.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives the tables, the assignments and the clauses; the statement returns no rows.
     */
    public function derive(Derivation $derivation): void
    {
        (new ChangeFacts())->update($this, $derivation, $derivation->environment());
    }

    /**
     * Describes the intrinsic collision without selecting a runtime error order.
     */
    public function message(): string
    {
        $problem = (new \SqlSemantics\Platform\MySql\Rules\Dml\Update\Structure())->names($this->tables);
        Check::invariant($problem !== null, 'A conflicting UPDATE keeps its repeated name.');
        return $problem->message();
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->node($this->with)->keyword('UPDATE');
        $comment = (new HintComment($this->hints))->text();
        if ($comment !== null) {
            $out->comment($comment);
        }
        if ($this->lowPriority) {
            $out->keyword('LOW_PRIORITY');
        }
        if ($this->ignore) {
            $out->keyword('IGNORE');
        }
        $out->list($this->tables)->keyword('SET')->list($this->assignments);
        if ($this->where !== null) {
            $out->keyword('WHERE')->node($this->where);
        }
        if ($this->orderBy !== []) {
            $out->keyword('ORDER', 'BY')->list($this->orderBy);
        }
        $out->node($this->limit);
    }
}
