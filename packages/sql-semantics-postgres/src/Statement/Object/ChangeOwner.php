<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Routine\ObjectFacts;
use SqlSemantics\Platform\PostgreSql\Rules\Routine\ObjectForms;
use SqlSemantics\Platform\PostgreSql\Rules\Routine\ObjectSpelling;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectReference;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `ALTER kind object OWNER TO role`: changes the owner of an object.
 *
 * Mirrors PostgreSQL's `AlterOwnerStmt`.
 * Source: https://www.postgresql.org/docs/17/sql-alterschema.html, https://www.postgresql.org/docs/17/sql-alterfunction.html.
 *
 * @visibility public
 * @example Reading the new owner
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER SCHEMA app OWNER TO CURRENT_USER');
 *     $operation->statement->owner->kind->name // => 'CurrentUser'
 */
final class ChangeOwner implements Statement
{
    use Snapshot;

    /**
     * @param ObjectKind $kind The kind of the object
     * @param ObjectReference $object The object, named as OWNER TO names objects of the kind
     * @param RoleSpec $owner The new owner
     */
    public function __construct(public readonly ObjectKind $kind, public readonly ObjectReference $object, public readonly RoleSpec $owner)
    {
        $forms = new ObjectForms();
        Check::input(in_array($forms->form($object), $forms->altered($kind, 'owner'), true), 'OWNER TO names the object as the grammar names objects of its kind.');
    }

    /**
     * Derives the object.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ObjectFacts())->derive($this->kind, [$this->object], false, $derivation);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER');
        (new ObjectSpelling())->kind($out, $this->kind);
        $out->node($this->object)->keyword('OWNER', 'TO')->node($this->owner);
    }
}
