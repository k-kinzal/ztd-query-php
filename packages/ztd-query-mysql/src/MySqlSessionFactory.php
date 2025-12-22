<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Connection\ConnectionInterface;
use ZtdQuery\Platform\MySql\Transformer\DeleteTransformer;
use ZtdQuery\Platform\MySql\Transformer\InsertTransformer;
use ZtdQuery\Platform\MySql\Transformer\MySqlTransformer;
use ZtdQuery\Platform\MySql\Transformer\ReplaceTransformer;
use ZtdQuery\Platform\MySql\Transformer\SelectTransformer;
use ZtdQuery\Platform\MySql\Transformer\UpdateTransformer;
use ZtdQuery\ResultSelectRunner;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Session;
use ZtdQuery\Platform\SessionFactory;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Factory for creating Session instances pre-configured for MySQL.
 */
final class MySqlSessionFactory implements SessionFactory
{
    /**
     * Create a Session pre-configured for MySQL.
     */
    public function create(ConnectionInterface $connection, ZtdConfig $config): Session
    {
        $shadowStore = new ShadowStore();
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

        $guard = new MySqlQueryGuard($parser);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($shadowStore, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        $rewriter = new MySqlRewriter($guard, $shadowStore, $registry, $transformer, $mutationResolver, $parser);

        return new Session(
            $rewriter,
            $shadowStore,
            new ResultSelectRunner(),
            $config,
            $connection
        );
    }
}
