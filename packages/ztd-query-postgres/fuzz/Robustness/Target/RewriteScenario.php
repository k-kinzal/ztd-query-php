<?php

declare(strict_types=1);

namespace Fuzz\Robustness\Target;

use Error;
use Fuzz\Fixture\FixtureDatabase;
use Fuzz\Fixture\RewriterFactory;
use Fuzz\Robustness\Invariant\ClassifyDeterministicChecker;
use Fuzz\Robustness\Invariant\ClassifyNeverThrowsChecker;
use Fuzz\Robustness\Invariant\ClassifyRewriteAgreementChecker;
use Fuzz\Robustness\Invariant\InvariantChecker;
use Fuzz\Robustness\Invariant\RewriteExceptionTypeChecker;
use Fuzz\Robustness\Invariant\RewritePlanConsistencyChecker;
use Fuzz\Robustness\Invariant\ShadowStoreConsistencyChecker;
use Fuzz\Robustness\Invariant\TruncateTargetConsistencyChecker;
use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\Postgres\PgSqlParser;
use ZtdQuery\Platform\Postgres\PgSqlQueryGuard;
use ZtdQuery\Platform\Postgres\PgSqlRewriter;
use ZtdQuery\Platform\Postgres\PgSqlSchemaParser;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Checks rewriting with fresh shadow, schema, view and identity state for every input.
 */
final class RewriteScenario
{
    private readonly ShadowStore $store;
    private readonly PgSqlRewriter $rewriter;
    /**
     * @var list<InvariantChecker>
     */
    private readonly array $checkers;

    /**
     * Creates isolated fixture state for a single fuzz input.
     */
    public function __construct()
    {
        $this->store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $fixtures = new FixtureDatabase();
        $fixtures->registerFixtureSchemas($registry, new PgSqlSchemaParser());
        $fixtures->populateFixtureData($this->store);
        $this->rewriter = (new RewriterFactory())->buildRewriter($this->store, $registry);
        $guard = new PgSqlQueryGuard(new PgSqlParser());
        $this->checkers = [
            new ClassifyNeverThrowsChecker($guard),
            new ClassifyDeterministicChecker($guard),
            new RewriteExceptionTypeChecker($this->rewriter),
            new RewritePlanConsistencyChecker($this->rewriter),
            new ClassifyRewriteAgreementChecker($guard, $this->rewriter),
            new TruncateTargetConsistencyChecker($this->rewriter),
        ];
    }

    /**
     * Checks query-plan invariants and optionally applies the simulated write.
     * @throws Error
     */
    public function verify(string $sql, bool $applyMutation): void
    {
        foreach ($this->checkers as $checker) {
            $violation = $checker->check($sql);
            if ($violation !== null) {
                throw new Error((string) $violation);
            }
        }
        if (!$applyMutation) {
            return;
        }
        try {
            $plan = $this->rewriter->rewrite($sql);
        } catch (UnsupportedSqlException|UnknownSchemaException) {
            return;
        }
        $mutation = $plan->mutation();
        if ($mutation !== null) {
            $mutation->apply($this->store, []);
        }
        $violation = (new ShadowStoreConsistencyChecker($this->store))->check($sql);
        if ($violation !== null) {
            throw new Error((string) $violation);
        }
    }

}
