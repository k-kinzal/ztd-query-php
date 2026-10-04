<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Table\ForeignKeys;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Writing;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Constraint;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintKind;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Reference\KeyMatch;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Reference\ReferentialAction;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Snapshot;

/**
 * A REFERENCES column constraint: a foreign key of one column.
 *
 * Mirrors `CONSTR_FOREIGN` with `pktable`, `pk_attrs`, `fk_matchtype` and the
 * actions. The referenced table is resolved (PG-FOREIGN-KEY-001); its facts
 * are the relation facts of this constraint.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html#SQL-CREATETABLE-PARMS-REFERENCES.
 *
 * @visibility public
 * @example Resolving the referenced table
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
 *     $parent = $semantics->analyze('CREATE TABLE p (id int PRIMARY KEY)');
 *     $child = $semantics->analyze('CREATE TABLE c (p int REFERENCES p (id) ON DELETE CASCADE)', $parent->declarations());
 *     $child->facts->relation($child->statement->definition->elements[0]->qualifiers[0])->table->table === $parent->declarations()[0] // => true
 */
final class References implements Constraint
{
    use Snapshot;

    /**
     * @var list<Name> The referenced columns; none means the primary key of the referenced table
     */
    public readonly array $columns;

    /**
     * @var list<ReferentialAction> The ON UPDATE and ON DELETE clauses, in the order written
     */
    public readonly array $actions;

    /**
     * @param QualifiedName $table The referenced table
     * @param list<Name> $columns The referenced columns
     * @param KeyMatch|null $match The match type, when written
     * @param list<ReferentialAction> $actions The ON UPDATE and ON DELETE clauses, in the order written
     * @param Name|null $name The constraint name
     */
    public function __construct(public readonly QualifiedName $table, array $columns = [], public readonly ?KeyMatch $match = null, array $actions = [], public readonly ?Name $name = null)
    {
        $this->columns = Check::listOf($columns, Name::class, 'Referenced columns are names.');
        $this->actions = (new ForeignKeys())->actions($actions);
    }

    /**
     * Answers the kind of the constraint.
     */
    public function kind(): ConstraintKind
    {
        return ConstraintKind::ForeignKey;
    }

    /**
     * Resolves the referenced table and its columns.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        (new ForeignKeys())->derive($derivation, $this, $this->table, $this->columns, $this->match, $this->actions);
    }

    /**
     * Writes the constraint.
     */
    public function render(Output $out): void
    {
        $writing = new Writing();
        $writing->constraintName($out, $this->name);
        $out->keyword('REFERENCES');
        (new ForeignKeys())->writeTarget($out, $this->table, $this->columns, $this->match, $this->actions);
    }
}
