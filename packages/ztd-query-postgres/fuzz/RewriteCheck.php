<?php

declare(strict_types=1);

namespace Fuzz;

use Error;
use Fuzz\Fixture\FixtureDatabase;
use Fuzz\Fixture\RewriterFactory;
use Fuzz\Input\TruncateTargets;
use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\Postgres\PgSqlParser;
use ZtdQuery\Platform\Postgres\PgSqlQueryGuard;
use ZtdQuery\Platform\Postgres\PgSqlSchemaParser;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\Mutation\MultiTruncateMutation;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Checks one rewrite against its routing and mutation contracts with isolated state.
 */
final class RewriteCheck
{
    /**
     * Accepts only documented unsupported SQL and missing-schema rejections.
     *
     * SQLFaker guarantees grammar generation, not names or result rows for this catalog.
     * The full target supplies an empty result set to exercise zero-row mutation handling;
     * it does not assert equivalence with PostgreSQL execution.
     *
     * @throws Error When an accepted rewrite violates its public plan contract
     */
    public function verify(string $sql, bool $applyMutation): void
    {
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $fixtures = new FixtureDatabase();
        $fixtures->registerFixtureSchemas($registry, new PgSqlSchemaParser());
        $fixtures->populateFixtureData($store);
        $rewriter = (new RewriterFactory())->buildRewriter($store, $registry);
        $kind = (new PgSqlQueryGuard(new PgSqlParser()))->classify($sql);
        try {
            $plan = $rewriter->rewrite($sql);
        } catch (UnsupportedSqlException|UnknownSchemaException) {
            return;
        }
        if ($plan->kind() !== $kind || $plan->sql() === '') {
            throw new Error('The accepted rewrite must preserve classification and return nonempty SQL.');
        }
        $mutation = $plan->mutation();
        $writes = $kind === QueryKind::WRITE_SIMULATED || $kind === QueryKind::DDL_SIMULATED;
        if ($writes !== ($mutation !== null)) {
            throw new Error('Only a simulated write or DDL plan may own a mutation.');
        }
        $targets = (new TruncateTargets())->targetCount($sql);
        if ($targets !== null && $targets > 1 && (!$mutation instanceof MultiTruncateMutation || count($mutation->tableNames()) !== $targets)) {
            throw new Error('TRUNCATE must retain every explicit target in its mutation.');
        }
        if ($applyMutation && $mutation !== null) {
            $mutation->apply($store, []);
            $rewriter->commitRewriteState();
            if (array_key_exists('', $store->getAll())) {
                throw new Error('Applying a mutation must not introduce an empty table name.');
            }
        }
    }
}
