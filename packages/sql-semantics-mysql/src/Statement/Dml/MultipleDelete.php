<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Rules\Dml\ChangeFacts;
use SqlSemantics\Platform\MySql\Statement\Query\WithClause;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * DELETE from several tables: the tables rows are deleted from, the table references that find the rows, and WHERE.
 *
 * Each table to delete from names a table of the references, by its
 * correlation name when it has one. The facts follow MYSQL-DELETE-001.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/delete.html.
 *
 * @visibility public
 * @example Reading a multiple-table DELETE
 *     $delete = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('DELETE t.* , u FROM t JOIN u ON t.a = u.a WHERE t.b = 1');
 *     [count($delete->statement->targets), $delete->toString()] // => [2, 'DELETE t, u FROM t JOIN u ON t.a = u.a WHERE t.b = 1']
 */
final class MultipleDelete implements Statement
{
    use Snapshot;

    /**
     * @var list<DeleteOption> The modifiers in written order
     */
    public readonly array $options;

    /**
     * @var list<QualifiedName> The tables rows are deleted from, in written order
     */
    public readonly array $targets;

    /**
     * @var list<Relation> The table references in written order
     */
    public readonly array $tables;

    /**
     * @param WithClause|null $with The common tables (MySQL 8.0 and later)
     * @param list<DeleteOption> $options The modifiers
     * @param list<QualifiedName> $targets The tables rows are deleted from; at least one
     * @param MultipleDeleteForm $form Where the tables to delete from are written
     * @param list<Relation> $tables The table references; at least one
     * @param Scalar|null $where The row predicate
     * @throws InvalidConstruction When there is no table to delete from or no table reference
     */
    public function __construct(
        public readonly ?WithClause $with,
        array $options,
        array $targets,
        public readonly MultipleDeleteForm $form,
        array $tables,
        public readonly ?Scalar $where = null,
    ) {
        $this->options = Check::listOf($options, DeleteOption::class, 'The modifiers of DELETE are delete options.');
        $this->targets = Check::listOf($targets, QualifiedName::class, 'A multiple-table DELETE names at least one table to delete from.', 1);
        foreach ($this->targets as $target) {
            Check::input($target->catalog === null, 'A table is qualified by at most a database.');
        }
        $this->tables = Check::listOf($tables, Relation::class, 'A multiple-table DELETE names at least one table reference.', 1);
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
