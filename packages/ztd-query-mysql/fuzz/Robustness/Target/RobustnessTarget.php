<?php

declare(strict_types=1);

namespace Fuzz\Robustness\Target;

use Error;
use Fuzz\Robustness\Invariant\ShadowStoreConsistencyChecker;
use Fuzz\Robustness\RewriteFixture;
use ZtdQuery\Exception\SimulationException;
use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;

/**
 * Exercises classification, rewrite invariants and simulated writes in isolated state.
 */
final class RobustnessTarget
{
    /**
     * Apply metadata mutations and verify store structure after every outcome.
     *
     * @throws Error
     */
    public function __invoke(string $sql): void
    {
        (new ClassifyTarget())($sql);
        (new RewriteTarget())($sql);
        $fixture = new RewriteFixture();
        try {
            try {
                $plan = $fixture->rewriter->rewrite($sql);
            } catch (UnsupportedSqlException | UnknownSchemaException) {
                return;
            }
            $mutation = $plan->mutation();
            if ($mutation === null) {
                return;
            }
            try {
                /**
                 * @throws SimulationException Domain rejection declared by concrete shadow mutations.
                 */
                $mutation->apply($fixture->store, []);
            } catch (SimulationException) {
                return;
            } finally {
                $violation = (new ShadowStoreConsistencyChecker($fixture->store))->check($sql);
                if ($violation !== null) {
                    throw new Error((string) $violation);
                }
            }
        } finally {
            $fixture->reset();
        }
    }
}
