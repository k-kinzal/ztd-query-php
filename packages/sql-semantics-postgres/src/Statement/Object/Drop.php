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
use SqlSemantics\Platform\PostgreSql\Statement\Object\Problem\ObjectProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Problem\ObjectProblemKind;
use SqlSemantics\Platform\PostgreSql\Statement\Option\DropBehavior;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `DROP kind [IF EXISTS] object, ... [CASCADE | RESTRICT]`: removes catalog objects of one kind.
 *
 * Mirrors PostgreSQL's `DropStmt` (`removeType`, `objects`, `behavior`,
 * `missing_ok`, `concurrent`), which also represents DROP FUNCTION,
 * AGGREGATE, OPERATOR, OPERATOR CLASS and FAMILY, CAST and TRANSFORM. How
 * the objects are named depends on the kind exactly as the grammar writes it
 * (PG-OBJECT-FORM-001). A dropped relation is resolved; dropping changes no
 * declaration of the context.
 * Source: https://www.postgresql.org/docs/17/sql-droptable.html, https://www.postgresql.org/docs/17/sql-dropfunction.html.
 *
 * @visibility public
 * @example Reading the dropped tables
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('DROP TABLE IF EXISTS a, b CASCADE');
 *     [$operation->statement->kind->value, count($operation->statement->objects), $operation->statement->ifExists] // => ['TABLE', 2, true]
 */
final class Drop implements Statement
{
    use Snapshot;

    /**
     * @var non-empty-list<ObjectReference> The dropped objects in written order
     */
    public readonly array $objects;

    /**
     * @param ObjectKind $kind The kind of the objects
     * @param list<ObjectReference> $objects The objects, named as DROP names objects of the kind; one only for the kinds named within a table, operator classes and families, casts and transforms
     * @param bool $ifExists Whether IF EXISTS is written
     * @param DropBehavior|null $behavior CASCADE or RESTRICT, if written
     * @param bool $concurrently Whether CONCURRENTLY is written; for indexes only
     */
    public function __construct(
        public readonly ObjectKind $kind,
        array $objects,
        public readonly bool $ifExists = false,
        public readonly ?DropBehavior $behavior = null,
        public readonly bool $concurrently = false,
    ) {
        $this->objects = Check::listOf($objects, ObjectReference::class, 'DROP names at least one object.', 1);
        $forms = new ObjectForms();
        foreach ($this->objects as $object) {
            Check::input(in_array($forms->form($object), $forms->drop($kind), true), 'DROP names each object as the grammar names objects of its kind.');
        }
        Check::input(count($this->objects) === 1 || !in_array($kind->value, ObjectForms::SINGLE_DROP, true), 'DROP names one object of this kind.');
        Check::input(!$concurrently || $kind === ObjectKind::Index, 'Only DROP INDEX is written CONCURRENTLY.');
    }

    /**
     * Derives the objects and reports concurrent drops the server rejects.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ObjectFacts())->derive($this->kind, $this->objects, $this->ifExists, $derivation);
        if ($this->concurrently && count($this->objects) > 1) {
            $derivation->report(new ObjectProblem(ObjectProblemKind::ConcurrentMultiple));
        }
        if ($this->concurrently && $this->behavior === DropBehavior::Cascade) {
            $derivation->report(new ObjectProblem(ObjectProblemKind::ConcurrentCascade));
        }
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('DROP');
        (new ObjectSpelling())->kind($out, $this->kind);
        if ($this->concurrently) {
            $out->keyword('CONCURRENTLY');
        }
        if ($this->ifExists) {
            $out->keyword('IF', 'EXISTS');
        }
        $out->list($this->objects);
        if ($this->behavior !== null) {
            $out->keyword($this->behavior->value);
        }
    }
}
