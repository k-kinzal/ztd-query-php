<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Routine\ObjectFacts;
use SqlSemantics\Platform\PostgreSql\Rules\Routine\ObjectForms;
use SqlSemantics\Platform\PostgreSql\Rules\Routine\ObjectSpelling;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectReference;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `ALTER kind object [NO] DEPENDS ON EXTENSION extension`: marks or unmarks an object as dependent on an extension.
 *
 * Mirrors PostgreSQL's `AlterObjectDependsStmt` (`remove` is NO).
 * Source: https://www.postgresql.org/docs/17/sql-alterfunction.html, https://www.postgresql.org/docs/17/sql-alterindex.html.
 *
 * @visibility public
 * @example Reading the removal of a dependency
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER INDEX i NO DEPENDS ON EXTENSION e');
 *     [$operation->statement->remove, $operation->statement->extension->value] // => [true, 'e']
 */
final class ExtensionDependency implements Statement
{
    use Snapshot;

    /**
     * @param ObjectKind $kind The kind of the object
     * @param ObjectReference $object The object, named as DEPENDS ON EXTENSION names objects of the kind
     * @param Name $extension The extension
     * @param bool $remove Whether NO is written: the dependency is removed
     */
    public function __construct(public readonly ObjectKind $kind, public readonly ObjectReference $object, public readonly Name $extension, public readonly bool $remove = false)
    {
        $forms = new ObjectForms();
        Check::input(in_array($forms->form($object), $forms->depends($kind), true), 'DEPENDS ON EXTENSION names the object as the grammar names objects of its kind.');
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
        $out->node($this->object);
        if ($this->remove) {
            $out->keyword('NO');
        }
        $out->keyword('DEPENDS', 'ON', 'EXTENSION')->name($this->extension, NameUse::Column);
    }
}
