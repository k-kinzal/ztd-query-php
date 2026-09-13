<?php

declare(strict_types=1);

namespace Fuzz\Robustness\Target;

use Fuzz\Robustness\Input\SqlInput;
use Fuzz\Support\FixtureDatabase;
use Fuzz\Support\RewriteFactory;
use SqlFaker\SqliteProvider;
use ZtdQuery\Platform\Sqlite\SqliteParser;
use ZtdQuery\Platform\Sqlite\SqliteQueryGuard;
use ZtdQuery\Platform\Sqlite\SqliteSchemaParser;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Verifies each rewrite once against a fresh schema registry and shadow store.
 */
final class RewriteTarget
{
    private readonly SqlInput $input;

    /**
     * Keeps SQLFaker grammar analysis outside the per-input callable.
     */
    public function __construct(SqliteProvider $provider)
    {
        $this->input = new SqlInput($provider);
    }

    /**
     * Discards all rewrite and mutation state after this input, including on failure.
     */
    public function __invoke(string $input): void
    {
        $sql = $this->input->sql($input);
        FuzzBoundary::run('rewrite plan', $input, $sql, static function () use ($sql): void {
            $store = new ShadowStore();
            $registry = new TableDefinitionRegistry();
            FixtureDatabase::registerFixtureSchemas($registry, new SqliteSchemaParser());
            foreach (FixtureDatabase::buildFixtureData() as $table => $rows) {
                $store->set($table, $rows);
            }
            RewriteCheck::verify(new SqliteQueryGuard(new SqliteParser()), RewriteFactory::create($store, $registry), $sql);
        });
    }
}
