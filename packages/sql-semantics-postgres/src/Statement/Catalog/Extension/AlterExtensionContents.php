<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Extension;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Catalog\ClauseFacts;
use SqlSemantics\Platform\PostgreSql\Rules\Table\RelationKinds;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectReference;
use SqlSemantics\Platform\PostgreSql\Statement\Option\AddOrDrop;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to add an object to an extension or to remove it from the extension.
 *
 * Rule: PG-EXTENSION-003. Mirrors `AlterExtensionContentsStmt`: action,
 * object kind and object. The object is referenced in the form its kind
 * takes: a name, a dotted name, a type, a cast, a routine or operator
 * signature, an operator class or family with its access method, or a
 * transform. Objects of a kind ALTER EXTENSION cannot address are rejected.
 * Source: https://www.postgresql.org/docs/17/sql-alterextension.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the object added to an extension
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER EXTENSION hstore ADD SCHEMA s');
 *     [$operation->statement->action, $operation->statement->kind] // => [\SqlSemantics\Platform\PostgreSql\Statement\Option\AddOrDrop::Add, \SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind::Schema]
 */
final class AlterExtensionContents implements Statement
{
    use Snapshot;

    /**
     * The kinds of object ALTER EXTENSION ADD and DROP accept.
     */
    private const KINDS = [
        ObjectKind::AccessMethod, ObjectKind::Aggregate, ObjectKind::Cast, ObjectKind::Collation, ObjectKind::Conversion, ObjectKind::Database,
        ObjectKind::Domain, ObjectKind::EventTrigger, ObjectKind::Extension, ObjectKind::ForeignDataWrapper, ObjectKind::ForeignServer,
        ObjectKind::ForeignTable, ObjectKind::Function, ObjectKind::Index, ObjectKind::Language, ObjectKind::MaterializedView,
        ObjectKind::OperatorClass, ObjectKind::Operator, ObjectKind::OperatorFamily, ObjectKind::Procedure, ObjectKind::Publication,
        ObjectKind::Role, ObjectKind::Routine, ObjectKind::Schema, ObjectKind::Sequence, ObjectKind::Statistics, ObjectKind::Subscription,
        ObjectKind::Table, ObjectKind::Tablespace, ObjectKind::TextSearchConfiguration, ObjectKind::TextSearchDictionary,
        ObjectKind::TextSearchParser, ObjectKind::TextSearchTemplate, ObjectKind::Transform, ObjectKind::Type, ObjectKind::View,
    ];

    /**
     * @param Name $extension The extension name
     * @param AddOrDrop $action Whether the object is added or removed
     * @param ObjectKind $kind The kind of the object
     * @param ObjectReference $object The object
     */
    public function __construct(public readonly Name $extension, public readonly AddOrDrop $action, public readonly ObjectKind $kind, public readonly ObjectReference $object)
    {
        Check::input(in_array($kind, self::KINDS, true), 'ALTER EXTENSION cannot address an object of this kind.');
    }

    /**
     * Derives the object reference, which may name types with modifiers.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ClauseFacts())->derive($derivation, [$this->object]);
        (new RelationKinds())->named($derivation, $this->kind, [$this->object]);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'EXTENSION')->name($this->extension, NameUse::Column)->keyword($this->action->value, ...$this->kind->keywords())->node($this->object);
    }
}
