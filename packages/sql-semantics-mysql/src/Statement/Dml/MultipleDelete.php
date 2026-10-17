<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Rules\Dml\ChangeFacts;
use SqlSemantics\Platform\MySql\Statement\Explainable;
use SqlSemantics\Platform\MySql\Statement\Hint\Comment\HintComment;
use SqlSemantics\Platform\MySql\Statement\Hint\OptimizerHint;
use SqlSemantics\Platform\MySql\Statement\Query\WithClause;
use SqlSemantics\Platform\MySql\Statement\Relation\TableSource as Relation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * DELETE from several tables: the tables rows are deleted from, the table references that find the rows, and WHERE.
 *
 * Each table to delete from names a table of the references, by its
 * correlation name when it has one. The facts follow MYSQL-DELETE-001. The
 * hints of the comment written right after DELETE are kept with the statement.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/delete.html.
 *
 * @visibility public
 * @example Reading a multiple-table DELETE
 *     $delete = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('DELETE t.* , u FROM t JOIN u ON t.a = u.a WHERE t.b = 1');
 *     [count($delete->statement->targets), $delete->toString()] // => [2, 'DELETE t, u FROM t JOIN u ON t.a = u.a WHERE t.b = 1']
 * @example Rejecting an intrinsically repeated target before deriving an operation
 *     $target = new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'));
 *     new \SqlSemantics\Platform\MySql\Statement\Dml\MultipleDelete(null, [], new \SqlSemantics\Platform\MySql\Statement\Dml\Target\DeletionInput([new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference($target)], [$target, $target]), \SqlSemantics\Platform\MySql\Statement\Dml\MultipleDeleteForm::BeforeFrom) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class MultipleDelete implements Explainable
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
     * @var non-empty-list<QualifiedName> The tables rows are deleted from, in written order
     */
    public readonly array $targets;

    /**
     * @var non-empty-list<Relation> The table references in written order
     */
    public readonly array $tables;

    /**
     * @param WithClause|null $with The common tables (MySQL 8.0 and later)
     * @param list<DeleteOption> $options The modifiers
     * @param Target\DeletionInput $input The coupled target and input namespace
     * @param MultipleDeleteForm $form Where the tables to delete from are written
     * @param Scalar|null $where The row predicate
     * @param list<OptimizerHint> $hints The hints of the comment written right after the verb, in written order
     * @throws InvalidConstruction When a target is repeated or absent from the input namespace, or targets or references are empty
     */
    public function __construct(
        public readonly ?WithClause $with,
        array $options,
        Target\DeletionInput $input,
        public readonly MultipleDeleteForm $form,
        public readonly ?Scalar $where = null,
        array $hints = [],
    ) {
        $this->hints = Check::listOf($hints, OptimizerHint::class, 'The hints of a statement are optimizer hints.');
        $this->options = Check::listOf($options, DeleteOption::class, 'The modifiers of DELETE are delete options.');
        $this->targets = $input->targets;
        $this->tables = $input->tables;
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives the table references, the tables to delete from and WHERE; the statement returns no rows.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ChangeFacts())->deleteMultiple($this, $derivation, $derivation->environment());
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
        if ($this->form === MultipleDeleteForm::Using) {
            $out->keyword('FROM');
        }
        foreach ($this->targets as $position => $target) {
            if ($position > 0) {
                $out->symbol(',');
            }
            if ($target->schema !== null) {
                $out->name($target->schema, NameUse::Qualifier)->symbol('.');
            }
            $out->name($target->name, NameUse::Relation);
        }
        $out->keyword($this->form === MultipleDeleteForm::Using ? 'USING' : 'FROM')->list($this->tables);
        if ($this->where !== null) {
            $out->keyword('WHERE')->node($this->where);
        }
    }
}
