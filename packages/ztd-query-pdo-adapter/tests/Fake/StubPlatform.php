<?php

declare(strict_types=1);

namespace Tests\Fake;

use ZtdQuery\Connection\ConnectionInterface;
use ZtdQuery\Platform;
use ZtdQuery\Platform\CopySupport;
use ZtdQuery\Platform\MissingResultColumnTypeResolver;
use ZtdQuery\Platform\ParameterBindingCompiler;
use ZtdQuery\Platform\ResultColumnTypeResolver;
use ZtdQuery\Rewrite\SqlRewriter;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Schema\ViewDefinitionSet;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Supplies controlled dialect services and exposes the state allocated by core.
 */
final class StubPlatform implements Platform
{
    /**
     * The virtual row store allocated by the executor, exposed for assertions.
     */
    public ShadowStore $store;

    /**
     * Retain the supplied services and optionally expose the core-owned row store.
     * @param-out ShadowStore $store
     */
    public function __construct(
        private readonly SqlRewriter $rewriter,
        private readonly ?CopySupport $copy = null,
        private readonly ?ParameterBindingCompiler $compiler = null,
        private readonly ResultColumnTypeResolver $resolver = new MissingResultColumnTypeResolver(),
        ?ShadowStore &$store = null,
    ) {
        $store ??= new ShadowStore();
        $this->store = &$store;
    }

    /**
     * {@inheritDoc}
     */
    public function reflectSchema(ConnectionInterface $connection): TableDefinitionRegistry
    {
        return new TableDefinitionRegistry();
    }

    /**
     * {@inheritDoc}
     */
    public function reflectViews(ConnectionInterface $connection): ViewDefinitionSet
    {
        return new ViewDefinitionSet();
    }

    /**
     * {@inheritDoc}
     */
    public function createRewriter(ShadowStore $store, TableDefinitionRegistry $registry, ViewDefinitionSet $views): SqlRewriter
    {
        $this->store = $store;
        return $this->rewriter;
    }

    /**
     * {@inheritDoc}
     */
    public function copySupport(): ?CopySupport
    {
        return $this->copy;
    }

    /**
     * {@inheritDoc}
     */
    public function parameterBindingCompiler(): ?ParameterBindingCompiler
    {
        return $this->compiler;
    }

    /**
     * {@inheritDoc}
     */
    public function resultColumnTypeResolver(): ResultColumnTypeResolver
    {
        return $this->resolver;
    }
}
