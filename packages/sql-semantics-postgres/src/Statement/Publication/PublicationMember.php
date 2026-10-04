<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Publication;

use SqlSemantics\Statement\Node;

/**
 * One item of the object list of CREATE or ALTER PUBLICATION: a table, or the tables of a schema.
 *
 * An item may omit its introducing keywords (TABLE or TABLES IN SCHEMA); it
 * then continues the kind of the item before it. The server reads the list
 * that way while parsing and rejects a list that starts without keywords.
 * Source: https://www.postgresql.org/docs/17/sql-createpublication.html.
 *
 * @visibility public
 * @example Telling whether an item writes its keywords
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Publication\PublicationSchema(null, true))->introduced() // => true
 */
interface PublicationMember extends Node
{
    /**
     * Tells whether TABLE or TABLES IN SCHEMA is written before the item.
     */
    public function introduced(): bool;
}
