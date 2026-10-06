<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Catalog\PublicationList;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to add, replace or remove tables and schemas of a publication.
 *
 * Rule: PG-PUBLICATION-002. Mirrors `AlterPublicationStmt` with an
 * `AlterPublicationAction` and an object list (PG-PUBLICATION-LIST-001);
 * removing a table with a row filter or column list is a diagnostic.
 * Source: https://www.postgresql.org/docs/17/sql-alterpublication.html. Status: Implemented.
 *
 * @visibility public
 * @example Removing a table with a row filter
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER PUBLICATION p DROP TABLE t WHERE (true)');
 *     $operation->facts->diagnostics[0]->message() // => 'cannot use a WHERE clause when removing a table from a publication'
 */
final class AlterPublicationMembers implements Statement
{
    use Snapshot;

    /**
     * @var non-empty-list<PublicationMember> The tables and schemas in the order written
     */
    public readonly array $objects;

    /**
     * @param Name $name The publication name
     * @param PublicationAction $action Whether the objects are added, replace the list, or are removed
     * @param list<PublicationMember> $objects The tables and schemas in the order written; at least one
     */
    public function __construct(public readonly Name $name, public readonly PublicationAction $action, array $objects)
    {
        $this->objects = Check::listOf($objects, PublicationMember::class, 'ALTER PUBLICATION names at least one object.', 1);
        (new PublicationList())->check($this->objects);
    }

    /**
     * Derives the tables and checks the list.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new PublicationList())->derive($derivation, $this->objects, $this->action);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'PUBLICATION')->name($this->name, NameUse::Column)->keyword($this->action->value)->list($this->objects);
    }
}
