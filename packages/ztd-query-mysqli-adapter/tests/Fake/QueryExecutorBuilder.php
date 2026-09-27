<?php

declare(strict_types=1);

namespace Tests\Fake;

use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Connection\ConnectionInterface;
use ZtdQuery\Platform;
use ZtdQuery\Platform\CopySupport;
use ZtdQuery\Platform\MissingResultColumnTypeResolver;
use ZtdQuery\Platform\ParameterBindingCompiler;
use ZtdQuery\Platform\ResultColumnTypeResolver;
use ZtdQuery\QueryExecutor;
use ZtdQuery\ResultSelectRunner;
use ZtdQuery\Rewrite\SqlRewriter;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Session;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Constructs the core executor with controlled dialect behavior for unit tests.
 */
final class QueryExecutorBuilder
{
    /**
     * Bind controlled dialect services to caller-supplied state.
     */
    public static function create(
        SqlRewriter $rewriter,
        ShadowStore $shadowStore,
        ResultSelectRunner $resultSelectRunner,
        ZtdConfig $config,
        ConnectionInterface $connection,
        ?TableDefinitionRegistry $registry = null,
        ?CopySupport $copySupport = null,
        ?ParameterBindingCompiler $parameterBindingCompiler = null,
        ResultColumnTypeResolver $resultColumnTypeResolver = new MissingResultColumnTypeResolver(),
    ): QueryExecutor {
        $platform = new StubPlatform($rewriter, $copySupport, $parameterBindingCompiler, $resultColumnTypeResolver);
        return new QueryExecutor($connection, $platform, $config, new Session($shadowStore, $registry ?? new TableDefinitionRegistry()), $resultSelectRunner);
    }
    /**
     * Supply controlled SQL behavior while letting core allocate the session.
     * @param-out ShadowStore $store
     */
    public static function platform(SqlRewriter $rewriter, ?ShadowStore &$store = null): Platform
    {
        $store ??= new ShadowStore();
        return new StubPlatform($rewriter, store: $store);
    }
}
