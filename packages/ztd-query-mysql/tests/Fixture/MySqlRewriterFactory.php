<?php

declare(strict_types=1);

namespace Tests\Fixture;

use LogicException;
use ZtdQuery\Platform\MySql\Rewrite\MySqlQueryGuard;
use ZtdQuery\Platform\MySql\Rewrite\MySqlRewriter;
use ZtdQuery\Platform\MySql\Rewrite\Transformer\DeleteTransformer;
use ZtdQuery\Platform\MySql\Rewrite\Transformer\InsertTransformer;
use ZtdQuery\Platform\MySql\Rewrite\Transformer\MySqlTransformer;
use ZtdQuery\Platform\MySql\Rewrite\Transformer\ReplaceTransformer;
use ZtdQuery\Platform\MySql\Rewrite\Transformer\SelectTransformer;
use ZtdQuery\Platform\MySql\Rewrite\Transformer\UpdateTransformer;
use ZtdQuery\Platform\MySql\Schema\MySqlSchemaParser;
use ZtdQuery\Platform\MySql\Shadow\MySqlMutationResolver;
use ZtdQuery\Platform\MySql\Sql\MySqlParser;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Builds MySQL rewriters for package-local unit fixtures.
 *
 * @internal
 */
final class MySqlRewriterFactory
{
    /**
     * Return a MySQL rewriter using the supplied shadow rows and schema registry.
     */
    public static function create(ShadowStore $store, TableDefinitionRegistry $registry): MySqlRewriter
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);
        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);
        $mutationResolver = new MySqlMutationResolver($store, $registry, $schemaParser, $updateTransformer, $deleteTransformer);
        return new MySqlRewriter(new MySqlQueryGuard($parser), $store, $registry, $transformer, $mutationResolver, $parser);
    }

    /**
     * Return a rewriter with the users schema, retaining any supplied rows and registry.
     * Omitted arguments create fresh state for each test.
     *
     * @throws LogicException
     */
    public static function withUsers(?ShadowStore $store = null, ?TableDefinitionRegistry $registry = null): MySqlRewriter
    {
        $store ??= new ShadowStore();
        $registry ??= new TableDefinitionRegistry();
        $definition = (new MySqlSchemaParser(new MySqlParser()))->parse(<<<'SQL'
CREATE TABLE users (
    id INT NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    PRIMARY KEY (id)
)
SQL);
        if ($definition === null) {
            throw new LogicException('Invalid MySQL users fixture.');
        }
        $registry->register('users', $definition);
        return self::create($store, $registry);
    }
}
