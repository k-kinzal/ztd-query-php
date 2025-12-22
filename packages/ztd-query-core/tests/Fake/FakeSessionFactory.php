<?php

declare(strict_types=1);

namespace Tests\Fake;

use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Connection\ConnectionInterface;
use ZtdQuery\ResultSelectRunner;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Session;
use ZtdQuery\Platform\SessionFactory;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Fake SessionFactory that assembles a Session using Fake platform implementations.
 *
 * Uses FakeSchemaParser and FakeSchemaReflector to pre-load schema,
 * then creates a Session with FakeSqlRewriter.
 */
final class FakeSessionFactory implements SessionFactory
{
    private FakeSchemaReflector $reflector;

    /**
     * @param FakeSchemaReflector|null $reflector Optional pre-configured reflector.
     */
    public function __construct(?FakeSchemaReflector $reflector = null)
    {
        $this->reflector = $reflector ?? new FakeSchemaReflector();
    }

    public function create(ConnectionInterface $connection, ZtdConfig $config): Session
    {
        $shadowStore = new ShadowStore();
        $parser = new FakeSchemaParser();
        $registry = new TableDefinitionRegistry();

        foreach ($this->reflector->reflectAll() as $tableName => $createSql) {
            $definition = $parser->parse($createSql);
            if ($definition !== null) {
                $registry->register($tableName, $definition);
            }
        }

        $rewriter = new FakeSqlRewriter($shadowStore, $registry);

        return new Session(
            $rewriter,
            $shadowStore,
            new ResultSelectRunner(),
            $config,
            $connection,
        );
    }
}
