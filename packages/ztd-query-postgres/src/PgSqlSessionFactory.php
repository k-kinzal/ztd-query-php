<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Postgres;

use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Connection\ConnectionInterface;
use ZtdQuery\Platform\Postgres\Dialect\PgSqlCopySupport;
use ZtdQuery\Platform\Postgres\Dialect\PgSqlPdoParameterBindingCompiler;
use ZtdQuery\Platform\Postgres\Dialect\PgSqlPdoResultColumnTypeResolver;
use ZtdQuery\Platform\Postgres\Parse\PgSqlParser;
use ZtdQuery\Platform\Postgres\Parse\PgSqlSchemaParser;
use ZtdQuery\Platform\Postgres\Rewrite\PgSqlMutationResolver;
use ZtdQuery\Platform\Postgres\Rewrite\PgSqlQueryGuard;
use ZtdQuery\Platform\Postgres\Rewrite\PgSqlRewriter;
use ZtdQuery\Platform\Postgres\Rewrite\PgSqlTransformer;
use ZtdQuery\Platform\Postgres\Transformer\DeleteTransformer;
use ZtdQuery\Platform\Postgres\Transformer\InsertTransformer;
use ZtdQuery\Platform\Postgres\Transformer\SelectTransformer;
use ZtdQuery\Platform\Postgres\Transformer\UpdateTransformer;
use ZtdQuery\Platform\SessionFactory;
use ZtdQuery\ResultSelectRunner;
use ZtdQuery\Session;
use ZtdQuery\Shadow\ShadowStore;
use ZtdQuery\Shadow\ShadowTransactions;

/**
 * Factory for creating Session instances pre-configured for PostgreSQL.
 */
final class PgSqlSessionFactory implements SessionFactory
{
    /**
     * {@inheritDoc}
     */
    public function create(ConnectionInterface $connection, ZtdConfig $config): Session
    {
        $shadowStore = new ShadowStore();
        $parser = new PgSqlParser();
        $reflector = new PgSqlSchemaReflector($connection);
        $schema = new PgSqlReflectedSchema();
        $registry = $schema->tables(
            $reflector->reflectAll(),
            $reflector->partialUniqueIndexes(),
            (new PgSqlPartitionReflector($connection))->reflect(),
        );

        $selectTransformer = new SelectTransformer();
        $transformer = new PgSqlTransformer(
            $parser,
            $selectTransformer,
            new InsertTransformer($parser, $selectTransformer),
            new UpdateTransformer($parser, $selectTransformer),
            new DeleteTransformer($parser, $selectTransformer),
        );
        $rewriter = new PgSqlRewriter(
            new PgSqlQueryGuard($parser),
            $shadowStore,
            $registry,
            $transformer,
            new PgSqlMutationResolver($shadowStore, $registry, new PgSqlSchemaParser(), $parser),
            $parser,
            $schema->views($reflector->reflectViews()),
        );

        return new Session(
            $rewriter,
            $shadowStore,
            new ResultSelectRunner(),
            $config,
            $connection,
            transactions: new ShadowTransactions($shadowStore, $registry),
            registry: $registry,
            copySupport: new PgSqlCopySupport(),
            parameterBindingCompiler: new PgSqlPdoParameterBindingCompiler(),
            resultColumnTypeResolver: new PgSqlPdoResultColumnTypeResolver(),
        );
    }
}
