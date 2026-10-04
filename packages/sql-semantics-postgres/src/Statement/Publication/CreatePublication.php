<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Publication;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Catalog\ClauseFacts;
use SqlSemantics\Platform\PostgreSql\Rules\Catalog\PublicationList;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Definition;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to create a publication.
 *
 * Rule: PG-PUBLICATION-001. Mirrors `CreatePublicationStmt`: name, either
 * FOR ALL TABLES or the object list (or neither), and the publication
 * parameters. The tables of the list are relation occurrences
 * (PG-PUBLICATION-TABLE-001); the list is read and checked by
 * PG-PUBLICATION-LIST-001. Source: https://www.postgresql.org/docs/17/sql-createpublication.html. Status: Implemented.
 *
 * @visibility public
 * @example Publishing every table
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("CREATE PUBLICATION p FOR ALL TABLES WITH (publish = 'insert')");
 *     [$operation->statement->allTables, $operation->toString()] // => [true, "CREATE PUBLICATION p FOR ALL TABLES WITH (publish = 'insert')"]
 */
final class CreatePublication implements Statement
{
    use Snapshot;

    /**
     * @var list<PublicationMember> The tables and schemas in the order written
     */
    public readonly array $objects;

    /**
     * @var list<Definition> The publication parameters
     */
    public readonly array $options;

    /**
     * @param Name $name The publication name
     * @param bool $allTables Whether FOR ALL TABLES is written
     * @param list<PublicationMember> $objects The tables and schemas in the order written; empty with FOR ALL TABLES
     * @param list<Definition> $options The publication parameters
     */
    public function __construct(public readonly Name $name, public readonly bool $allTables = false, array $objects = [], array $options = [])
    {
        $this->objects = Check::listOf($objects, PublicationMember::class, 'Publication objects are a list.');
        $this->options = Check::listOf($options, Definition::class, 'Publication parameters are definitions.');
        Check::input(!$allTables || $this->objects === [], 'FOR ALL TABLES has no object list.');
        (new PublicationList())->check($this->objects);
    }

    /**
     * Derives the published tables and the parameters and checks the list.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new PublicationList())->derive($derivation, $this->objects, null);
        (new ClauseFacts())->derive($derivation, $this->options);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('CREATE', 'PUBLICATION')->name($this->name, NameUse::Column);
        if ($this->allTables) {
            $out->keyword('FOR', 'ALL', 'TABLES');
        } elseif ($this->objects !== []) {
            $out->keyword('FOR')->list($this->objects);
        }
        if ($this->options !== []) {
            $out->keyword('WITH')->symbol('(')->list($this->options)->symbol(')');
        }
    }
}
