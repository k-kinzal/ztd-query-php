<?php

declare(strict_types=1);

namespace Fuzz\Robustness\Target;

use Error;

/**
 * Checks rewrite-plan invariants using raw SQL and structural grammar mutations.
 */
final class RobustnessTarget
{
    private \Fuzz\Robustness\Input\SqlInput $input;

    /**
     * Retains immutable grammar planning; generated plans do not use Faker's later state.
     */
    public function __construct(\SqlFaker\SqliteProvider $provider)
    {
        $this->input = new \Fuzz\Robustness\Input\SqlInput($provider);
    }

    /**
     * Runs the contract in a fresh fixture environment for this input.
     */
    public function __invoke(string $input): void
    {
        $sql = $this->input->sql($input);
        FuzzBoundary::run('RobustnessTarget', $input, $sql, function () use ($sql): void {
            $guard = new \ZtdQuery\Platform\Sqlite\SqliteQueryGuard(new \ZtdQuery\Platform\Sqlite\SqliteParser());
            $store = new \ZtdQuery\Shadow\ShadowStore();
            $registry = new \ZtdQuery\Schema\TableDefinitionRegistry();
            \Fuzz\Support\FixtureDatabase::registerFixtureSchemas($registry, new \ZtdQuery\Platform\Sqlite\SqliteSchemaParser());
            foreach (\Fuzz\Support\FixtureDatabase::buildFixtureData() as $table => $rows) {
                $store->set($table, $rows);
            }
            $rewriter = \Fuzz\Support\RewriteFactory::create($store, $registry);
            $checkers = [
                new \Fuzz\Robustness\Invariant\ClassifyNeverThrowsChecker($guard),
                new \Fuzz\Robustness\Invariant\ClassifyDeterministicChecker($guard),
                new \Fuzz\Robustness\Invariant\RewriteExceptionTypeChecker($rewriter),
                new \Fuzz\Robustness\Invariant\RewritePlanConsistencyChecker($rewriter),
                new \Fuzz\Robustness\Invariant\ClassifyRewriteAgreementChecker($guard, $rewriter),
            ];
            foreach ($checkers as $checker) {
                $violation = $checker->check($sql);
                if ($violation !== null) {
                    throw new Error((string) $violation);
                }
            }
            try {
                $plan = $rewriter->rewrite($sql);
            } catch (\ZtdQuery\Exception\UnsupportedSqlException | \ZtdQuery\Exception\UnknownSchemaException) {
                return;
            }
            $plan->mutation()?->apply($store, []);
            $violation = (new \Fuzz\Robustness\Invariant\ShadowStoreConsistencyChecker($store))->check($sql);
            if ($violation !== null) {
                throw new Error((string) $violation);
            }
        });
    }
}
