<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Alter;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Attributes;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Writing;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintAttribute;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * ALTER CONSTRAINT: changes the deferral attributes of a constraint.
 *
 * Mirrors `AT_AlterConstraint`. The server accepts this only for foreign keys; the conflicts of the
 * attributes are checked by PG-CONSTRAINT-ATTRIBUTES-001.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 *
 * @visibility public
 * @example Changing the deferral of a constraint
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TABLE t ALTER CONSTRAINT fk DEFERRABLE INITIALLY DEFERRED');
 *     $statement->toString() // => 'ALTER TABLE t ALTER CONSTRAINT fk DEFERRABLE INITIALLY DEFERRED'
 */
final class AlterConstraint implements AlterCommand
{
    use Snapshot;

    /**
     * @var list<ConstraintAttribute> The attributes in the order written
     */
    public readonly array $attributes;

    /**
     * @param Name $name The constraint
     * @param list<ConstraintAttribute> $attributes The attributes in the order written
     */
    public function __construct(public readonly Name $name, array $attributes)
    {
        $this->attributes = (new Attributes())->checked($attributes);
    }

    /**
     * Checks the attributes.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        (new Attributes())->report($derivation, $this->attributes, 'FOREIGN KEY', true, false, false);
    }

    /**
     * Writes the action.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'CONSTRAINT')->name($this->name);
        (new Writing())->sequence($out, $this->attributes);
    }
}
