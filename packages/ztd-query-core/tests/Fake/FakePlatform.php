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
 * Minimal database semantics for core tests, without session construction.
 */
final class FakePlatform implements Platform
{
    /**
     * Retain the catalog source for reflection.
     */
    public function __construct(private readonly FakeSchemaReflector $reflector = new FakeSchemaReflector())
    {
    }

    /**
     * {@inheritDoc}
     */
    public function reflectSchema(ConnectionInterface $connection): TableDefinitionRegistry
    {
        $registry = new TableDefinitionRegistry();
        $parser = new FakeSchemaParser();
        foreach ($this->reflector->reflectAll() as $name => $sql) {
            $definition = $parser->parse($sql);
            if ($definition !== null) {
                $registry->register($name, $definition);
            }
        }
        return $registry;
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
        return new FakeSqlRewriter($store, $registry);
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
        return new MissingResultColumnTypeResolver();
    }
}
