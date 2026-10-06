<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Schema;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Catalog\SchemaMember;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Problem\CatalogMisuse;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Problem\CatalogMisuseRule;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec;
use SqlSemantics\Platform\PostgreSql\Statement\Table\SchemaElement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to create a schema, optionally with the objects created in it.
 *
 * Rule: PG-SCHEMA-001. Mirrors `CreateSchemaStmt`: schema name (the owner's
 * name when only AUTHORIZATION is written), owner, IF NOT EXISTS and the
 * schema elements (CREATE TABLE, CREATE INDEX, CREATE SEQUENCE, CREATE
 * TRIGGER, CREATE VIEW and GRANT) in the order written. IF NOT EXISTS with
 * elements and a name starting with `pg_` are diagnostics. The schema is
 * the written name, or the name of the role written after AUTHORIZATION.
 * Each CREATE element is derived as an element of that schema: an
 * unqualified object it creates is declared in the new schema, and an
 * object qualified with another schema is a diagnostic. When the schema
 * name is not written (AUTHORIZATION CURRENT_USER and the like), it depends
 * on the session, so the elements keep their facts and diagnostics but
 * declare nothing. Every element is read with the new schema searched
 * first and sees the relations of the elements the server runs before it
 * (PG-SCHEMA-ELEMENT-001).
 * Source: https://www.postgresql.org/docs/17/sql-createschema.html. Status: Implemented.
 *
 * @visibility public
 * @example Creating a schema for a role
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE SCHEMA IF NOT EXISTS AUTHORIZATION joe');
 *     [$operation->statement->name, $operation->statement->authorization?->name?->value] // => [null, 'joe']
 * @example Declaring a table in the new schema
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE SCHEMA AUTHORIZATION joe CREATE TABLE t (a int4)');
 *     $operation->declarations()[0]->name->schema?->value // => 'joe'
 */
final class CreateSchema implements Statement
{
    use Snapshot;

    /**
     * @var list<Statement> The schema elements in the order written
     */
    public readonly array $elements;

    /**
     * @param Name|null $name The schema name; null when only AUTHORIZATION is written
     * @param RoleSpec|null $authorization The owner, when AUTHORIZATION is written
     * @param list<Statement> $elements The schema elements in the order written
     * @param bool $ifNotExists Whether IF NOT EXISTS is written
     */
    public function __construct(public readonly ?Name $name, public readonly ?RoleSpec $authorization = null, array $elements = [], public readonly bool $ifNotExists = false)
    {
        $this->elements = Check::listOf($elements, Statement::class, 'Schema elements are statements.');
        Check::input($name !== null || $authorization !== null, 'A schema is named or owned.');
    }

    /**
     * Derives the elements and reports the problems of the request.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        if ($this->ifNotExists && $this->elements !== []) {
            $derivation->report(new CatalogMisuse(CatalogMisuseRule::SchemaElementsIfNotExists));
        }
        if ($this->name !== null && str_starts_with($this->name->value, 'pg_')) {
            $derivation->report(new CatalogMisuse(CatalogMisuseRule::ReservedSchemaName, [$this->name->value]));
        }
        $schema = $this->name ?? $this->authorization?->name;
        $members = [];
        foreach ($this->elements as $element) {
            if ($schema === null) {
                $element instanceof SchemaElement ? $derivation->inspected($element) : $derivation->statement($element);
                continue;
            }
            $written = $element instanceof SchemaElement ? $element->createdSchema() : null;
            if ($written !== null && !$derivation->context->relationNames->equal($written->value, $schema->value)) {
                $derivation->report(new CatalogMisuse(CatalogMisuseRule::ElementSchemaMismatch, [$written->value, $schema->value]));
            }
            $members[] = new SchemaMember($element, $schema);
        }
        usort($members, static fn (SchemaMember $one, SchemaMember $other): int => $one->step() <=> $other->step());
        foreach ($members as $member) {
            $derivation->within($member->context($derivation->context, $derivation->facts()->declarations), $member);
        }
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('CREATE', 'SCHEMA');
        if ($this->ifNotExists) {
            $out->keyword('IF', 'NOT', 'EXISTS');
        }
        if ($this->name !== null) {
            $out->name($this->name, NameUse::Column);
        }
        if ($this->authorization !== null) {
            $out->keyword('AUTHORIZATION')->node($this->authorization);
        }
        foreach ($this->elements as $element) {
            $out->node($element);
        }
    }
}
