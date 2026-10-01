<?php

declare(strict_types=1);

namespace ZtdQuery;

use ZtdQuery\Connection\ConnectionInterface;
use ZtdQuery\Platform\CopySupport;
use ZtdQuery\Platform\ParameterBindingCompiler;
use ZtdQuery\Platform\ResultColumnTypeResolver;
use ZtdQuery\Rewrite\SqlRewriter;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Schema\ViewDefinitionSet;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Database semantics used by the core executor and driver adapters.
 *
 * Implementations provide dialect services, never sessions or execution policy.
 * Reusing a platform must not share virtual rows, schema, or rewrite state:
 * each createRewriter() call binds a new pipeline to the supplied state.
 *
 * @visibility public
 * @example Accept database semantics through dependency injection
 *     $connect = static fn (\ZtdQuery\Connection\ConnectionInterface $connection, \ZtdQuery\Platform $platform): \ZtdQuery\QueryExecutor => new \ZtdQuery\QueryExecutor($connection, $platform);
 *     $connect instanceof \Closure // => true
 */
interface Platform
{
    /**
     * Reflect the table catalog; propagate database failures to the caller.
     */
    public function reflectSchema(ConnectionInterface $connection): TableDefinitionRegistry;

    /**
     * Reflect view definitions independently of virtual session state.
     */
    public function reflectViews(ConnectionInterface $connection): ViewDefinitionSet;

    /**
     * Bind a fresh dialect rewrite pipeline to the supplied virtual state.
     */
    public function createRewriter(ShadowStore $store, TableDefinitionRegistry $registry, ViewDefinitionSet $views): SqlRewriter;

    /**
     * Return COPY support, or null when the dialect does not support COPY.
     */
    public function copySupport(): ?CopySupport;

    /**
     * Return parameter compilation when the dialect requires it.
     */
    public function parameterBindingCompiler(): ?ParameterBindingCompiler;

    /**
     * Resolve column metadata reported by the connection driver.
     */
    public function resultColumnTypeResolver(): ResultColumnTypeResolver;
}
