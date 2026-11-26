<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Attributes;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Writing;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Constraint;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintKind;

/**
 * A UNIQUE or PRIMARY KEY constraint built on an existing unique index: `USING INDEX name`.
 *
 * Mirrors `CONSTR_UNIQUE`/`CONSTR_PRIMARY` with `indexname`. The key columns
 * are those of the index, which a context does not declare; the server
 * accepts this form only in ALTER TABLE ... ADD.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 *
 * @visibility SqlSemantics
 */
final class IndexConstraint implements Constraint
{
    use Snapshot;

    /**
     * @var list<ConstraintAttribute> The attributes in the order written
     */
    public readonly array $attributes;

    /**
     * @param bool $primary Whether the constraint is a primary key; a unique constraint otherwise
     * @param Name $index The index
     * @param list<ConstraintAttribute> $attributes The attributes in the order written
     * @param Name|null $name The constraint name
     */
    public function __construct(public readonly bool $primary, public readonly Name $index, array $attributes = [], public readonly ?Name $name = null)
    {
        $this->attributes = (new Attributes())->checked($attributes);
    }

    /**
     * Answers the kind of the constraint.
     */
    public function kind(): ConstraintKind
    {
        return $this->primary ? ConstraintKind::PrimaryKey : ConstraintKind::Unique;
    }

    /**
     * Checks the attributes.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        (new Attributes())->report($derivation, $this->attributes, $this->primary ? 'PRIMARY KEY' : 'UNIQUE', true, false, false);
    }

    /**
     * Writes the constraint.
     */
    public function render(Output $out): void
    {
        $writing = new Writing();
        $writing->constraintName($out, $this->name);
        $out->keyword(...($this->primary ? ['PRIMARY', 'KEY'] : ['UNIQUE']))->keyword('USING', 'INDEX')->name($this->index);
        $writing->sequence($out, $this->attributes);
    }
}
