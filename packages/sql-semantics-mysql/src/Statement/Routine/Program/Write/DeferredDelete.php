<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Write;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Dml\ChangeFacts;
use SqlSemantics\Platform\MySql\Statement\Dml\DeleteOption;
use SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget;
use SqlSemantics\Platform\MySql\Statement\Hint\Comment\HintComment;
use SqlSemantics\Platform\MySql\Statement\Hint\OptimizerHint;
use SqlSemantics\Platform\MySql\Statement\Query\Limit;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Platform\MySql\Statement\Query\WithClause;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A stored single-table DELETE whose target resolves to its own common table.
 *
 * The complete instruction is retained until invocation; ordinary Delete values
 * exclude this intrinsically read-only target role.
 *
 * @visibility public
 * @example Retaining a common-table deletion in a stored program
 *     $definition = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p() WITH c AS (SELECT 1) DELETE FROM c');
 *     $definition->statement->body instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredDelete // => true
 */
final class DeferredDelete implements \SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement
{
    use Snapshot;

    /**
     * @var list<OptimizerHint> The hints of the comment written right after the verb, in written order
     */
    public readonly array $hints;

    /**
     * @var list<DeleteOption> The modifiers in written order
     */
    public readonly array $options;

    /**
     * @var list<OrderItem> The ORDER BY items in written order
     */
    public readonly array $orderBy;

    /**
     * @param WithClause|null $with The common tables (MySQL 8.0 and later)
     * @param list<DeleteOption> $options The modifiers
     * @param WriteTarget $table The table rows are deleted from
     * @param Scalar|null $where The row predicate
     * @param list<OrderItem> $orderBy The order the rows are deleted in
     * @param Limit|null $limit The most rows deleted
     * @param list<OptimizerHint> $hints The hints of the comment written right after the verb, in written order
     */
    public function __construct(
        public readonly ?WithClause $with,
        array $options,
        public readonly WriteTarget $table,
        public readonly ?Scalar $where = null,
        array $orderBy = [],
        public readonly ?Limit $limit = null,
        array $hints = [],
    ) {
        $this->hints = Check::listOf($hints, OptimizerHint::class, 'The hints of a statement are optimizer hints.');
        $this->options = Check::listOf($options, DeleteOption::class, 'The modifiers of DELETE are delete options.');
        $this->orderBy = Check::listOf($orderBy, OrderItem::class, 'ORDER BY holds ordering items.');
        foreach ($this->orderBy as $item) {
            Check::input($item->expression instanceof Scalar, 'DELETE ordering uses expressions, not output-column positions.');
        }
        Check::input((new \SqlSemantics\Platform\MySql\Rules\Dml\CommonTargets())->contains($with, $table->name), 'A deferred single-table DELETE retains a common-table target.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives the table and the clauses; the statement returns no rows.
     */
    public function deriveProgram(Derivation $derivation, \SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope $scope): void
    {
        (new ChangeFacts())->delete($this, $derivation, $scope->environment);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->node($this->with)->keyword('DELETE');
        $comment = (new HintComment($this->hints))->text();
        if ($comment !== null) {
            $out->comment($comment);
        }
        foreach ($this->options as $option) {
            $out->keyword($option->value);
        }
        $out->keyword('FROM')->node($this->table);
        if ($this->where !== null) {
            $out->keyword('WHERE')->node($this->where);
        }
        if ($this->orderBy !== []) {
            $out->keyword('ORDER', 'BY')->list($this->orderBy);
        }
        $out->node($this->limit);
    }
}
