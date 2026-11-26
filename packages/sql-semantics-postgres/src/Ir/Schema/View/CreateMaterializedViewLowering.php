<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\View;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\DefinitionQueryLowering;
use SqlSemantics\Platform\PostgreSql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Statement\Table\View\CreateMaterializedView as ParsedCreateMaterializedView;

/**
 * Resolves a persistent heap materialized view declaration.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class CreateMaterializedViewLowering
{
    /**
     * Publishes a closed materialization policy and defining query.
     */
    public function lower(BoundStatement $operation, ParsedCreateMaterializedView $statement): CreateMaterializedView
    {
        $context = $operation->context;
        Check::input($context->profile->parameters === ParameterStyle::Native, 'CREATE MATERIALIZED VIEW requires the native profile.');
        Check::input(
            !$statement->unlogged && ($statement->method === null || $statement->method->value === 'heap')
            && $statement->options === [] && $statement->tablespace === null && $statement->name->catalog === null,
            'CREATE MATERIALIZED VIEW requires persistent native heap storage and the default tablespace.'
        );
        $schema = ($statement->name->schema ?? $context->declarationSchema)->value;
        $name = new ViewName(new Identifier($schema), new Identifier($statement->name->name->value));
        [$query, $columns] = (new DefinitionQueryLowering())->lower($operation, $statement->query, $statement->columns);

        return new CreateMaterializedView($name, $query, $columns, $statement->ifNotExists, $statement->withData ?? true);
    }
}
