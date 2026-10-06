<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Routine\ObjectFacts;
use SqlSemantics\Platform\PostgreSql\Rules\Routine\ObjectForms;
use SqlSemantics\Platform\PostgreSql\Rules\Routine\ObjectSpelling;
use SqlSemantics\Platform\PostgreSql\Rules\Routine\RelationTargets;
use SqlSemantics\Platform\PostgreSql\Rules\Table\RelationKinds;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectReference;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\RelationTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Option\DropBehavior;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `ALTER kind object RENAME [part name] TO new_name`: renames an object or one of its columns, constraints or attributes.
 *
 * Mirrors PostgreSQL's `RenameStmt` (`renameType`, `relationType`, object,
 * `subname`, `newname`, `behavior`, `missing_ok`). How the object is named
 * depends on the kind (PG-OBJECT-FORM-001). A renamed relation is resolved;
 * renaming a column the declared relation lacks, or to a name it has, is
 * reported. Renaming changes no declaration of the context.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html, https://www.postgresql.org/docs/17/sql-alterfunction.html.
 *
 * @visibility public
 * @example Reading a column rename
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TABLE t RENAME COLUMN a TO b');
 *     [$operation->statement->member?->name->value, $operation->statement->newName->value] // => ['a', 'b']
 */
final class Rename implements Statement
{
    use Snapshot;

    /**
     * @param ObjectKind $kind The kind of the object
     * @param ObjectReference $object The object, named as ALTER ... RENAME names objects of the kind
     * @param Name $newName The new name
     * @param RenamedMember|null $member The renamed column, constraint or attribute, if the object itself is not renamed
     * @param bool $ifExists Whether IF EXISTS is written
     * @param DropBehavior|null $behavior CASCADE or RESTRICT, written only when renaming an attribute
     * @param RoleWord|null $roleWord The keyword naming a role; given exactly for roles
     */
    public function __construct(
        public readonly ObjectKind $kind,
        public readonly ObjectReference $object,
        public readonly Name $newName,
        public readonly ?RenamedMember $member = null,
        public readonly bool $ifExists = false,
        public readonly ?DropBehavior $behavior = null,
        public readonly ?RoleWord $roleWord = null,
    ) {
        $forms = new ObjectForms();
        Check::input(in_array($forms->form($object), $forms->altered($kind, 'rename'), true), 'ALTER ... RENAME names the object as the grammar names objects of its kind.');
        Check::input(($kind === ObjectKind::Role) === ($roleWord !== null), 'A role is renamed with ROLE, USER or GROUP; nothing else is.');
        Check::input(!$ifExists || $kind === ObjectKind::Policy || in_array($kind->value, ObjectForms::ALTERED_RELATION, true), 'ALTER ... RENAME accepts IF EXISTS for policies and relations only.');
        Check::input($member === null || in_array($kind->value, match ($member->part) {
            RenamedPart::Column => ['TABLE', 'VIEW', 'MATERIALIZED VIEW', 'FOREIGN TABLE'],
            RenamedPart::Constraint => ['TABLE', 'DOMAIN'],
            RenamedPart::Attribute => ['TYPE'],
        }, true), 'The renamed part belongs to the kind of the object.');
        Check::input(!$ifExists || $member?->part !== RenamedPart::Constraint || $kind === ObjectKind::Table, 'A domain constraint is renamed without IF EXISTS.');
        Check::input($behavior === null || $member?->part === RenamedPart::Attribute, 'CASCADE or RESTRICT is written only when renaming an attribute.');
    }

    /**
     * Derives the object and reports a renamed column the declared relation lacks or a new column name it has.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        [$fact] = (new ObjectFacts())->derive($this->kind, [$this->object], $this->ifExists, $derivation);
        (new RelationKinds())->renamed($derivation, $this->kind, $this->object, true, $this->member !== null && $this->member->part !== RenamedPart::Attribute);
        if ($fact !== null && $this->member?->part === RenamedPart::Column && $this->object instanceof RelationTarget) {
            $targets = new RelationTargets();
            $targets->member($fact, $this->member->name, $this->object->relation->name, $derivation);
            $targets->renamed($fact, $this->newName, $derivation);
        }
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER');
        if ($this->roleWord !== null) {
            $out->keyword($this->roleWord->value);
        } else {
            (new ObjectSpelling())->kind($out, $this->kind);
        }
        if ($this->ifExists) {
            $out->keyword('IF', 'EXISTS');
        }
        $out->node($this->object)->keyword('RENAME')->node($this->member)->keyword('TO')->name($this->newName, NameUse::Column);
        if ($this->behavior !== null) {
            $out->keyword($this->behavior->value);
        }
    }
}
