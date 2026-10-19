<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Rules\Dml\ChangeFacts;
use SqlSemantics\Platform\MySql\Statement\Explainable;
use SqlSemantics\Platform\MySql\Statement\Hint\Comment\HintComment;
use SqlSemantics\Platform\MySql\Statement\Hint\OptimizerHint;
use SqlSemantics\Platform\MySql\Statement\Query\Limit;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Platform\MySql\Statement\Query\WithClause;
use SqlSemantics\Platform\MySql\Statement\Relation\TableSource as Relation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * UPDATE of one table or of several tables, with SET assignments, WHERE, ORDER BY and LIMIT.
 *
 * Mirrors the server's `PT_update`: the statement is a multiple-table
 * UPDATE when its table references hold more than one table. The facts
 * follow MYSQL-UPDATE-001. The hints of the comment written right after
 * UPDATE are kept with the statement.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/update.html.
 *
 * @visibility public
 * @example Reading an UPDATE
 *     $update = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('UPDATE LOW_PRIORITY t SET a = a + 1 WHERE b = 2 ORDER BY a LIMIT 10');
 *     [count($update->statement->assignments), $update->statement->where !== null, $update->toString()] // => [1, true, 'UPDATE LOW_PRIORITY t SET a = a + 1 WHERE b = 2 ORDER BY a LIMIT 10']
 * @example Refusing contradictory input columns before constructing an operation
 *     $table = new \SqlSemantics\Platform\MySql\Statement\Relation\JsonTable(new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral(), new \SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral(['$']), [new \SqlSemantics\Platform\MySql\Statement\Call\Json\OrdinalityColumn(new \SqlSemantics\Statement\Identifier\Name('b')), new \SqlSemantics\Platform\MySql\Statement\Call\Json\OrdinalityColumn(new \SqlSemantics\Statement\Identifier\Name('B'))], new \SqlSemantics\Statement\Identifier\Name('j'));
 *     $assignment = new \SqlSemantics\Platform\MySql\Statement\Dml\Assignment(new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse(new \SqlSemantics\Statement\Identifier\Name('b')), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'));
 *     new \SqlSemantics\Platform\MySql\Statement\Dml\Update(null, false, false, [$table], [$assignment]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing an empty read row before operation construction
 *     $table = new \SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable(new \SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery([new \SqlSemantics\Platform\MySql\Statement\Query\ValueRow([])]), new \SqlSemantics\Statement\Identifier\Name('q'));
 *     $assignment = new \SqlSemantics\Platform\MySql\Statement\Dml\Assignment(new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse(new \SqlSemantics\Statement\Identifier\Name('a')), new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral());
 *     new \SqlSemantics\Platform\MySql\Statement\Dml\Update(null, false, false, [$table], [$assignment]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class Update implements Explainable
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
     * @var non-empty-list<Assignment> The assignments in written order
     */
    public readonly array $assignments;

    /**
     * @var list<OrderItem> The ORDER BY items in written order
     */
    public readonly array $orderBy;

    /**
     * @param WithClause|null $with The common tables (MySQL 8.0 and later)
     * @param bool $lowPriority Whether LOW_PRIORITY is written
     * @param bool $ignore Whether IGNORE turns errors into warnings
     * @param list<Relation> $tables The table references; at least one
     * @param list<Assignment> $assignments The assignments; at least one
     * @param Scalar|null $where The row predicate
     * @param list<OrderItem> $orderBy The order the rows are updated in
     * @param Limit|null $limit The most rows updated
     * @param list<OptimizerHint> $hints The hints of the comment written right after the verb, in written order
     * @throws InvalidConstruction When there is no table or no assignment
     */
    public function __construct(
        public readonly ?WithClause $with,
        public readonly bool $lowPriority,
        public readonly bool $ignore,
        array $tables,
        array $assignments,
        public readonly ?Scalar $where = null,
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
        $this->assignments = Check::listOf($assignments, Assignment::class, 'UPDATE holds at least one assignment.', 1);
        $this->orderBy = Check::listOf($orderBy, OrderItem::class, 'ORDER BY holds ordering items.');
        foreach ($this->orderBy as $item) {
            Check::input($item->expression instanceof Scalar, 'UPDATE ordering uses expressions, not output-column positions.');
        }
        Check::input((new \SqlSemantics\Platform\MySql\Rules\Query\From\InputColumns())->of($this) === null, 'An ordinary UPDATE has a coherent input-column namespace.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives the tables, the assignments and the clauses; the statement returns no rows.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ChangeFacts())->update($this, $derivation, $derivation->environment());
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
