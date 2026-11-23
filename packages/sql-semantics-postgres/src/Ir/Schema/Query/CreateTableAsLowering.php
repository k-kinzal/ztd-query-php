<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Query;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\DefinitionQueryLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\TableName;
use SqlSemantics\Platform\PostgreSql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTableAs as ParsedCreateTableAs;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Persistence;

/**
 * Resolves CREATE TABLE AS under the native persistent heap profile.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class CreateTableAsLowering
{
    /**
     * Publishes the resolved definition and optional initial data policy.
     */
    public function lower(BoundStatement $operation, ParsedCreateTableAs $statement): CreateTableAs
    {
        $context = $operation->context;
        $target = $statement->target;
        $policy = $statement->persistence;
        Check::input($context->profile->parameters === ParameterStyle::Native, 'CREATE TABLE AS requires the native profile.');
        Check::input($policy === Persistence::Permanent || $policy === Persistence::Unlogged, 'CREATE TABLE AS requires a persistent table.');
        Check::input(
            ($target->method === null || $target->method->value === 'heap') && $target->options === []
                && $target->tablespace === null && $target->onCommit === null && $target->name->catalog === null,
            'CREATE TABLE AS requires native heap storage, the default tablespace and no commit action.',
        );
        $schema = ($target->name->schema ?? $context->declarationSchema)->value;
        $name = new TableName(new Identifier($schema), new Identifier($target->name->name->value));
        [$query, $columns] = (new DefinitionQueryLowering())->lower($operation, $statement->query, $target->columns);

        return new CreateTableAs($name, $query, $columns, $policy === Persistence::Unlogged, $statement->ifNotExists, $statement->withData ?? true);
    }
}
