<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use PhpMyAdmin\SqlParser\Context;
use ZtdQuery\Connection\ConnectionInterface;
use ZtdQuery\Platform;
use ZtdQuery\Platform\CopySupport;
use ZtdQuery\Platform\MySql\Connection\MySqlSessionSqlModeReflector;
use ZtdQuery\Platform\MySql\Connection\Result\MySqlResultColumnTypeResolver;
use ZtdQuery\Platform\MySql\Rewrite\MySqlQueryGuard;
use ZtdQuery\Platform\MySql\Rewrite\MySqlRewriter;
use ZtdQuery\Platform\MySql\Rewrite\Transformer\DeleteTransformer;
use ZtdQuery\Platform\MySql\Rewrite\Transformer\InsertTransformer;
use ZtdQuery\Platform\MySql\Rewrite\Transformer\MySqlTransformer;
use ZtdQuery\Platform\MySql\Rewrite\Transformer\ReplaceTransformer;
use ZtdQuery\Platform\MySql\Rewrite\Transformer\SelectTransformer;
use ZtdQuery\Platform\MySql\Rewrite\Transformer\UpdateTransformer;
use ZtdQuery\Platform\MySql\Schema\MySqlSchemaParser;
use ZtdQuery\Platform\MySql\Schema\MySqlSchemaReflector;
use ZtdQuery\Platform\MySql\Shadow\MySqlMutationResolver;
use ZtdQuery\Platform\MySql\Sql\MySqlParser;
use ZtdQuery\Platform\ParameterBindingCompiler;
use ZtdQuery\Platform\ResultColumnTypeResolver;
use ZtdQuery\Rewrite\SqlRewriter;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Schema\ViewDefinitionSet;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Database semantics for MySQL.
 *
 * Inject this platform into a QueryExecutor or a driver adapter. Each executor owns
 * its session state and receives an independent rewrite pipeline.
 * @visibility public
 * @example Inject database semantics without creating a session
 *     $platform = new \ZtdQuery\Platform\MySql\MySqlPlatform();
 *     $platform instanceof \ZtdQuery\Platform // => true
 */
final class MySqlPlatform implements Platform
{
    /**
     * {@inheritDoc}
     */
    public function reflectSchema(ConnectionInterface $connection): TableDefinitionRegistry
    {
        Context::setMode((new MySqlSessionSqlModeReflector($connection))->reflect());

        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $registry = new TableDefinitionRegistry();

        $reflector = new MySqlSchemaReflector($connection);
        foreach ($reflector->reflectAll() as $tableName => $createSql) {
            $definition = $schemaParser->parse($createSql);
            if ($definition !== null) {
                $registry->register($tableName, $definition);
            }
        }

        return $registry;
    }

    /**
     * {@inheritDoc}
     */
    public function reflectViews(ConnectionInterface $connection): ViewDefinitionSet
    {
        $reflector = new MySqlSchemaReflector($connection);
        $views = new ViewDefinitionSet();
        foreach ($reflector->reflectViews() as $viewName => $definition) {
            $views->register($viewName, $definition);
        }

        return $views;
    }

    /**
     * {@inheritDoc}
     */
    public function createRewriter(ShadowStore $store, TableDefinitionRegistry $registry, ViewDefinitionSet $views): SqlRewriter
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);

        $guard = new MySqlQueryGuard($parser);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($store, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        return new MySqlRewriter($guard, $store, $registry, $transformer, $mutationResolver, $parser, $views);
    }

    /**
     * {@inheritDoc}
     */
    public function copySupport(): ?CopySupport
    {
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function parameterBindingCompiler(): ?ParameterBindingCompiler
    {
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function resultColumnTypeResolver(): ResultColumnTypeResolver
    {
        return new MySqlResultColumnTypeResolver();
    }
}
