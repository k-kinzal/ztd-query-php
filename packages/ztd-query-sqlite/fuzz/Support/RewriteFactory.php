<?php

declare(strict_types=1);

namespace Fuzz\Support;

use ZtdQuery\Platform\Sqlite\SqliteCastRenderer;
use ZtdQuery\Platform\Sqlite\SqliteIdentifierQuoter;
use ZtdQuery\Platform\Sqlite\SqliteMutationResolver;
use ZtdQuery\Platform\Sqlite\SqliteParser;
use ZtdQuery\Platform\Sqlite\SqliteQueryGuard;
use ZtdQuery\Platform\Sqlite\SqliteRewriter;
use ZtdQuery\Platform\Sqlite\SqliteSchemaParser;
use ZtdQuery\Platform\Sqlite\Transformer\DeleteTransformer;
use ZtdQuery\Platform\Sqlite\Transformer\InsertTransformer;
use ZtdQuery\Platform\Sqlite\Transformer\SelectTransformer;
use ZtdQuery\Platform\Sqlite\Transformer\SqliteTransformer;
use ZtdQuery\Platform\Sqlite\Transformer\UpdateTransformer;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Composes a fresh SQLite rewrite pipeline around caller-owned fixtures and schema state.
 */
final class RewriteFactory
{
    /**
     * Build a fresh rewriter with given registry and shadow store.
     */
    public static function create(ShadowStore $shadowStore, TableDefinitionRegistry $registry): SqliteRewriter
    {
        $parser = new SqliteParser();
        $guard = new SqliteQueryGuard($parser);
        $castRenderer = new SqliteCastRenderer();
        $quoter = new SqliteIdentifierQuoter();
        $selectTransformer = new SelectTransformer($castRenderer, $quoter);
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $schemaParser = new SqliteSchemaParser();
        $mutationResolver = new SqliteMutationResolver($shadowStore, $registry, $schemaParser, $parser);
        return new SqliteRewriter($guard, $shadowStore, $registry, $transformer, $mutationResolver, $parser);
    }

}
