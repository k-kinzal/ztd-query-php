<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;
use SqlSemantics\Platform\PostgreSql\Rules\Catalog\PublicationList;

/**
 * A request to add, replace or remove tables and schemas of a publication.
 *
 * Rule: PG-PUBLICATION-002. Mirrors `AlterPublicationStmt` with an
 * `AlterPublicationAction` and an object list (PG-PUBLICATION-LIST-001);
 * removing a table with a row filter or column list is a diagnostic.
 * Source: https://www.postgresql.org/docs/17/sql-alterpublication.html. Status: Implemented.
 *
 * @visibility SqlSemantics
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
