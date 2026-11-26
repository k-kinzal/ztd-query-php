<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;
use SqlSemantics\Platform\PostgreSql\Rules\Routine\ObjectFacts;
use SqlSemantics\Platform\PostgreSql\Rules\Routine\ObjectForms;
use SqlSemantics\Platform\PostgreSql\Rules\Routine\ObjectSpelling;
use SqlSemantics\Platform\PostgreSql\Rules\Table\RelationKinds;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectReference;

/**
 * `ALTER kind object SET SCHEMA schema`: moves an object to another schema.
 *
 * Mirrors PostgreSQL's `AlterObjectSchemaStmt`. A moved relation is resolved;
 * the move changes no declaration of the context.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html, https://www.postgresql.org/docs/17/sql-alterfunction.html.
 *
 * @visibility SqlSemantics
 */
final class SetSchema implements Statement
{
    use Snapshot;

    /**
     * @param ObjectKind $kind The kind of the object
     * @param ObjectReference $object The object, named as SET SCHEMA names objects of the kind
     * @param Name $schema The new schema
     * @param bool $ifExists Whether IF EXISTS is written; for relations only
     */
    public function __construct(public readonly ObjectKind $kind, public readonly ObjectReference $object, public readonly Name $schema, public readonly bool $ifExists = false)
    {
        $forms = new ObjectForms();
        Check::input(in_array($forms->form($object), $forms->altered($kind, 'schema'), true), 'SET SCHEMA names the object as the grammar names objects of its kind.');
        Check::input(!$ifExists || in_array($kind->value, ObjectForms::ALTERED_RELATION, true), 'SET SCHEMA accepts IF EXISTS for relations only.');
    }

    /**
     * Derives the object.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ObjectFacts())->derive($this->kind, [$this->object], $this->ifExists, $derivation);
        (new RelationKinds())->renamed($derivation, $this->kind, $this->object, false, false);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER');
        (new ObjectSpelling())->kind($out, $this->kind);
        if ($this->ifExists) {
            $out->keyword('IF', 'EXISTS');
        }
        $out->node($this->object)->keyword('SET', 'SCHEMA')->name($this->schema, NameUse::Column);
    }
}
