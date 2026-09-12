<?php

declare(strict_types=1);

namespace Fuzz\Robustness\Target;

use Error;
use Fuzz\Robustness\Invariant\ClassifyRewriteAgreementChecker;
use Fuzz\Robustness\Invariant\RewritePlanConsistencyChecker;
use Fuzz\Robustness\RewriteFixture;

/**
 * Checks plan invariants and agreement between classification and rewriting.
 */
final class RewriteTarget
{
    /**
     * Verify one statement with independent, disposable shadow state.
     *
     * @throws Error
     */
    public function __invoke(string $sql): void
    {
        $fixture = new RewriteFixture();
        try {
            $checks = [
                new RewritePlanConsistencyChecker($fixture->rewriter),
                new ClassifyRewriteAgreementChecker($fixture->guard, $fixture->rewriter),
            ];
            foreach ($checks as $check) {
                $violation = $check->check($sql);
                if ($violation !== null) {
                    throw new Error((string) $violation);
                }
            }
        } finally {
            $fixture->reset();
        }
    }
}
