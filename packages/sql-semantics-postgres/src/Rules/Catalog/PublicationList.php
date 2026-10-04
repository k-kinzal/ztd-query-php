<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Catalog;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Problem\CatalogMisuse;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Problem\CatalogMisuseRule;
use SqlSemantics\Platform\PostgreSql\Statement\Publication\PublicationAction;
use SqlSemantics\Platform\PostgreSql\Statement\Publication\PublicationMember;
use SqlSemantics\Platform\PostgreSql\Statement\Publication\PublicationSchema;
use SqlSemantics\Platform\PostgreSql\Statement\Publication\PublicationTable;
use SqlSemantics\Resolution\Environment;

/**
 * Reads and checks the object list of CREATE and ALTER PUBLICATION.
 *
 * Rule: PG-PUBLICATION-LIST-001. An item without TABLE or TABLES IN SCHEMA
 * continues the kind of the item before it, as the grammar's
 * `preprocess_pubobj_list` does: a bare name after a schema item is a schema,
 * any other unintroduced name is a table, and CURRENT_SCHEMA alone is the
 * current schema. A list cannot start without keywords ("invalid
 * publication object list"); CURRENT_SCHEMA cannot continue tables
 * ("invalid table name"); a table written with a qualifier, ONLY, a star, a
 * column list or a row filter cannot continue schemas ("WHERE clause not
 * allowed for schema", "column specification not allowed for schema",
 * "invalid schema name"). Removing tables with a row filter or a column list
 * is rejected. A list whose rendering would be read with another kind is
 * not constructed. Termination: one pass over a finite list.
 * Source: https://www.postgresql.org/docs/17/sql-createpublication.html, https://www.postgresql.org/docs/17/sql-alterpublication.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class PublicationList
{
    /**
     * Checks that each item renders as the kind the list would read it as.
     *
     * @param list<PublicationMember> $objects
     */
    public function check(array $objects): void
    {
        $previous = null;
        foreach ($objects as $object) {
            if ($object instanceof PublicationSchema && !$object->keywords && $object->schema !== null) {
                Check::input($previous instanceof PublicationSchema, 'A schema name without TABLES IN SCHEMA continues a schema item.');
            }
            if ($object instanceof PublicationTable && $object->bare()) {
                Check::input(!$previous instanceof PublicationSchema, 'A bare table name continues a table item.');
            }
            $previous = $object;
        }
    }

    /**
     * Derives the items and reports the problems of the list.
     *
     * @param list<PublicationMember> $objects
     */
    public function derive(Derivation $derivation, array $objects, ?PublicationAction $action): void
    {
        $previous = null;
        foreach ($objects as $object) {
            if ($object instanceof PublicationTable) {
                $derivation->relation($object, new Environment($derivation->context));
            }
            $rule = $this->rule($previous, $object, $action);
            if ($rule !== null) {
                $derivation->report(new CatalogMisuse($rule));
            }
            $previous = $object;
        }
    }

    /**
     * Answers the rule an item breaks after the item before it, or null.
     */
    public function rule(?PublicationMember $previous, PublicationMember $object, ?PublicationAction $action): ?CatalogMisuseRule
    {
        if ($previous === null && !$object->introduced()) {
            return CatalogMisuseRule::PublicationListStart;
        }
        if ($object instanceof PublicationSchema) {
            return !$object->keywords && $previous instanceof PublicationTable ? CatalogMisuseRule::PublicationTableName : null;
        }
        if ($object instanceof PublicationTable && !$object->keyword && $previous instanceof PublicationSchema) {
            return match (true) {
                $object->where !== null => CatalogMisuseRule::PublicationSchemaWhere,
                $object->columns !== [] => CatalogMisuseRule::PublicationSchemaColumns,
                default => CatalogMisuseRule::PublicationSchemaName,
            };
        }
        if ($object instanceof PublicationTable && $action === PublicationAction::Drop) {
            return match (true) {
                $object->where !== null => CatalogMisuseRule::PublicationDropWhere,
                $object->columns !== [] => CatalogMisuseRule::PublicationDropColumns,
                default => null,
            };
        }

        return null;
    }
}
