<?php

declare(strict_types=1);

namespace SqlFaker\Fuzz\Target;

use SqlFaker\Coverage\Verification\VerificationResult;

/**
 * Independent parser verification distinguishes findings, inconclusive semantics and unavailable infrastructure.
 */
interface SyntaxCheck
{
    /**
     * @throws SyntaxFailure When the parser rejects generated syntax or an unclassified restriction
     * @throws InfrastructureFailure When the parser environment cannot perform verification
     */
    public function verify(string $sql, string $input): VerificationResult;
}
