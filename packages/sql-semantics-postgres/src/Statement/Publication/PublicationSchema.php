<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Publication;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * The tables of a schema as an item of a publication: `TABLES IN SCHEMA { name | CURRENT_SCHEMA }`.
 *
 * Mirrors `PublicationObjSpec` of type TABLES_IN_SCHEMA or
 * TABLES_IN_CUR_SCHEMA. The schema is not part of a declaration context.
 * Source: https://www.postgresql.org/docs/17/sql-createpublication.html.
 *
 * @visibility public
 * @example Publishing the tables of the current schema
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE PUBLICATION p FOR TABLES IN SCHEMA CURRENT_SCHEMA, s');
 *     [$operation->statement->objects[0]->schema, $operation->statement->objects[1]->schema?->value] // => [null, 's']
 */
final class PublicationSchema implements PublicationMember
{
    use Snapshot;

    /**
     * @param Name|null $schema The schema; null for CURRENT_SCHEMA
     * @param bool $keywords Whether TABLES IN SCHEMA is written
     */
    public function __construct(public readonly ?Name $schema, public readonly bool $keywords = true)
    {
    }

    /**
     * Tells whether TABLES IN SCHEMA is written.
     */
    public function introduced(): bool
    {
        return $this->keywords;
    }

    /**
     * Writes the item.
     */
    public function render(Output $out): void
    {
        if ($this->keywords) {
            $out->keyword('TABLES', 'IN', 'SCHEMA');
        }
        if ($this->schema === null) {
            $out->keyword('CURRENT_SCHEMA');

            return;
        }
        $out->name($this->schema, NameUse::Column);
    }
}
