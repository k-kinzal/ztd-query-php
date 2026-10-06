<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Attributes;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\GeneratedColumns;
use SqlSemantics\Platform\PostgreSql\Rules\Table\ForeignKeys;
use SqlSemantics\Platform\PostgreSql\Rules\Table\KeyClauses;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Writing;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Constraint;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintKind;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Reference\KeyMatch;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Reference\ReferentialAction;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Snapshot;

/**
 * A FOREIGN KEY table constraint: the referencing columns hold values of the referenced columns.
 *
 * Mirrors `CONSTR_FOREIGN` with `fk_attrs`, `pktable`, `pk_attrs`,
 * `fk_matchtype`, the actions and the attributes. The referencing columns
 * must be columns of the table; the referenced table is resolved
 * (PG-FOREIGN-KEY-001) and is the relation fact of this constraint.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html#SQL-CREATETABLE-PARMS-REFERENCES.
 *
 * @visibility public
 * @example Reading a foreign key
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TABLE t (a int, FOREIGN KEY (a) REFERENCES u (id) MATCH SIMPLE ON DELETE CASCADE ON UPDATE NO ACTION NOT VALID)');
 *     $create->toString() // => 'CREATE TABLE t (a INT, FOREIGN KEY (a) REFERENCES u (id) MATCH SIMPLE ON DELETE CASCADE ON UPDATE NO ACTION NOT VALID)'
 */
final class ForeignKey implements Constraint
{
    use Snapshot;

    /**
     * @var non-empty-list<Name> The referencing columns
     */
    public readonly array $columns;

    /**
     * @var list<Name> The referenced columns; none means the primary key of the referenced table
     */
    public readonly array $referenced;

    /**
     * @var list<ReferentialAction> The ON UPDATE and ON DELETE clauses, in the order written
     */
    public readonly array $actions;

    /**
     * @var list<ConstraintAttribute> The attributes in the order written
     */
    public readonly array $attributes;

    /**
     * @param list<Name> $columns The referencing columns; at least one
     * @param QualifiedName $table The referenced table
     * @param list<Name> $referenced The referenced columns
     * @param KeyMatch|null $match The match type, when written
     * @param list<ReferentialAction> $actions The ON UPDATE and ON DELETE clauses, in the order written
     * @param list<ConstraintAttribute> $attributes The attributes in the order written
     * @param Name|null $name The constraint name
     */
    public function __construct(array $columns, public readonly QualifiedName $table, array $referenced = [], public readonly ?KeyMatch $match = null, array $actions = [], array $attributes = [], public readonly ?Name $name = null)
    {
        $this->columns = Check::listOf($columns, Name::class, 'A foreign key has at least one referencing column.', 1);
        $this->referenced = Check::listOf($referenced, Name::class, 'Referenced columns are names.');
        $this->actions = (new ForeignKeys())->actions($actions);
        $this->attributes = (new Attributes())->checked($attributes);
    }

    /**
     * Answers the kind of the constraint.
     */
    public function kind(): ConstraintKind
    {
        return ConstraintKind::ForeignKey;
    }

    /**
     * Checks the referencing columns, resolves the referenced table and checks the attributes.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        (new KeyClauses())->derive($derivation, $environment, $this->columns, []);
        (new ForeignKeys())->derive($derivation, $this, $this->table, $this->referenced, $this->match, $this->actions);
        $generated = new GeneratedColumns();
        if ($generated->among($derivation, $environment, $this->columns)) {
            $generated->keyActions($derivation, $this->actions);
        }
        (new Attributes())->report($derivation, $this->attributes, 'FOREIGN KEY', true, true, false);
    }

    /**
     * Writes the constraint.
     */
    public function render(Output $out): void
    {
        $writing = new Writing();
        $writing->constraintName($out, $this->name);
        $out->keyword('FOREIGN', 'KEY');
        $writing->parenthesized($out, $this->columns);
        $out->keyword('REFERENCES');
        (new ForeignKeys())->writeTarget($out, $this->table, $this->referenced, $this->match, $this->actions);
        $writing->sequence($out, $this->attributes);
    }
}
