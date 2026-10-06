<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Statement;

/**
 * A statement that CREATE SCHEMA may contain: CREATE TABLE, CREATE INDEX, CREATE SEQUENCE, CREATE TRIGGER or CREATE VIEW.
 *
 * Inside CREATE SCHEMA, an unqualified object (or the unqualified table of
 * CREATE INDEX and CREATE TRIGGER) belongs to the schema being created; a
 * different written schema is an error the containing statement reports.
 * Source: https://www.postgresql.org/docs/17/sql-createschema.html.
 *
 * @visibility public
 * @example Reading the schema written on an element
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TABLE s.t (a int)');
 *     $create->statement->createdSchema()?->value // => 's'
 */
interface SchemaElement extends Statement
{
    /**
     * Answers the schema written on the created object (on the table, for an index or a trigger), or null when it is unqualified.
     */
    public function createdSchema(): ?Name;

    /**
     * Derives the statement as an element of CREATE SCHEMA: an unqualified object is in the given schema.
     */
    public function deriveElement(Derivation $derivation, Name $schema): void;
}
