<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication;

use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * The tables of a schema as an item of a publication: `TABLES IN SCHEMA { name | CURRENT_SCHEMA }`.
 *
 * Mirrors `PublicationObjSpec` of type TABLES_IN_SCHEMA or
 * TABLES_IN_CUR_SCHEMA. The schema is not part of a declaration context.
 * Source: https://www.postgresql.org/docs/17/sql-createpublication.html.
 *
 * @visibility SqlSemantics
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
