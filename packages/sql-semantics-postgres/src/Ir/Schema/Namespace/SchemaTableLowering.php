<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\CreateTable;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\CreateTableLowering;
use SqlSemantics\Platform\PostgreSql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Facade\SchemaMemberScope;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Schema\CreateSchema;
use SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTable as ParsedCreateTable;

/**
 * Resolves ordinary table declarations in the namespace being created.
 *
 * @visibility SqlSemantics
 */
final class SchemaTableLowering
{
    /**
     * Binds each checked table in the same order as PostgreSQL's table phase.
     *
     * @return list<CreateTable>
     */
    public function lower(AnalysisContext $context, CreateSchema $schema, ?Identifier $resolved): array
    {
        if ($schema->elements === []) {
            return [];
        }
        Check::input($resolved !== null && !$schema->ifNotExists, 'Schema tables require an unguarded, statically named namespace.');
        $name = new Name($resolved->value);
        $resolvedTables = [];
        $provided = [];
        $seen = [];
        $lowering = new CreateTableLowering();
        $scope = new SchemaMemberScope();
        foreach ($schema->elements as $element) {
            Check::input($element instanceof ParsedCreateTable, 'CREATE SCHEMA elements require a checked namespace composition.');
            $binding = new BoundStatement($scope->bind($context, $name, $provided), $element);
            $table = $lowering->lower($binding, $element);
            $resolvedTables[] = $table;
            $coordinate = $table->target->table->value;
            if (!isset($seen[$coordinate])) {
                $seen[$coordinate] = true;
                array_push($provided, ...$binding->facts->declarations);
            }
        }

        return $resolvedTables;
    }
}
