<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Facade\Index;

use SqlSemantics\Platform\PostgreSql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\IndexSchemas;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;

/**
 * Locates relation declarations while preserving the catalog state of their input position.
 *
 * @visibility SqlSemantics
 */
final class CatalogRelationCoordinates
{
    /**
     * Retains the schema selected at declaration time as the search path grows.
     */
    public function absolute(Table $table, Name $schema): Table
    {
        if ($table->name->schema !== null) {
            return $table;
        }

        return new Table(new QualifiedName($table->name->name, $schema), $table->profile, $table->columns, $table->implicit, $table->complete, $table->kind);
    }

    /**
     * Tells whether an implicit declaration can definitely be assigned its chosen schema.
     */
    public function definite(AnalysisContext $context, Name $schema): bool
    {
        if (!$context->indexNamespace->known($schema)) {
            return false;
        }
        if ($context->complete) {
            return true;
        }
        foreach ($context->searchPath as $searched) {
            if ($searched->value === $schema->value) {
                return true;
            }
            if ($searched->value !== 'pg_catalog' && $searched->value !== 'pg_temp' && !IndexSchemas::stock($searched->value)) {
                return false;
            }
        }

        return false;
    }

    /**
     * Identifies the exact relation of a bound table declaration or index definition.
     *
     * @return array{Name, Name}
     */
    public function owner(BoundStatement $binding, Statement $statement, ?Name $enclosing): array
    {
        $table = $binding->facts->relation($statement)->table;
        Check::input($table instanceof DeclaredTable, 'Catalog indexes require a declared indexed relation.');
        $name = $table->table->name;
        Check::input($name->catalog === null, 'Catalog relations must have a local identity.');

        return [$name->schema ?? $enclosing ?? $binding->context->declarationSchema, $name->name];
    }

    /**
     * Keys an already resolved relation by its schema and name.
     */
    public function key(Name $schema, Name $name): string
    {
        return $schema->value . "\0" . $name->value;
    }
}
