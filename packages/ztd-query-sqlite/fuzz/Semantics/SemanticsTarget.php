<?php

declare(strict_types=1);

namespace Fuzz\Semantics;

use Error;
use Fuzz\Robustness\Target\FuzzBoundary;
use Fuzz\Support\RewriteFactory;
use ZtdQuery\Platform\Sqlite\SqliteSchemaParser;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Compares rewritten SELECT and simulated DML with native SQLite after every operation.
 */
final class SemanticsTarget
{
    /**
     * Retains SQLFaker's grammar analysis between independent command sequences.
     */
    public function __construct(private readonly CommandSequence $sequence)
    {
    }

    /**
     * Runs one bounded sequence; local connections and all mutable state expire on return or failure.
     */
    public function __invoke(string $input): void
    {
        $commands = $this->sequence->compile($input);
        FuzzBoundary::run('SemanticsTarget', $input, implode(";\n", $commands), static function () use ($commands): void {
            $schema = 'CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, score INTEGER NOT NULL)';
            $databases = new DatabasePair($schema);
            $definition = (new SqliteSchemaParser())->parse($schema);
            if ($definition === null) {
                throw new Error('Fixture schema could not be reflected');
            }
            $registry = new TableDefinitionRegistry();
            $registry->register('users', $definition);
            $store = new ShadowStore();
            $store->set('users', [['id' => 1, 'name' => 'Alice', 'score' => 10], ['id' => 2, 'name' => 'Bob', 'score' => 20]]);
            $rewriter = RewriteFactory::create($store, $registry);
            foreach ($commands as $sql) {
                $native = DatabasePair::query($databases->native, $sql);
                $plan = $rewriter->rewrite($sql);
                $rows = DatabasePair::query($databases->physical, $plan->sql());
                if ($plan->kind() === QueryKind::READ && $rows !== $native) {
                    throw new Error('Native/rewritten result mismatch: ' . var_export([$native, $rows], true));
                }
                $plan->mutation()?->apply($store, $rows);
                $rewriter->commitRewriteState();
                $databases->compareState($store);
            }
        });
    }
}
