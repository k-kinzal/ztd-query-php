<?php

declare(strict_types=1);

namespace SqlFaker\Coverage\Verification;

/**
 * One independent parser observation; only accepted credits accepted-output coverage.
 */
final class VerificationResult
{
    /**
     * Retains the diagnostic independently of its classification.
     * @param 'accepted'|'semantic-inconclusive'|'unsupported'|'finding'|'infrastructure-failure' $status
     */
    public function __construct(
        public readonly string $status,
        public readonly string $code = '',
        public readonly string $message = '',
        public readonly string $source = '',
    ) {
    }
}
