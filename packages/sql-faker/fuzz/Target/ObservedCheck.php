<?php

declare(strict_types=1);

namespace SqlFaker\Fuzz\Target;

use SqlFaker\Coverage\Verification\VerificationCoverage;
use SqlFaker\Coverage\Verification\VerificationResult;

/**
 * Records the independent verdict, preserving findings before native fuzzing handles the exception.
 */
final class ObservedCheck
{
    /**
     * Binds the parser contract and its independent coverage recorder.
     */
    public function __construct(private readonly SyntaxCheck $check, private readonly VerificationCoverage $coverage)
    {
    }

    /**
     * @throws SyntaxFailure When the parser reports a finding
     * @throws InfrastructureFailure When verification cannot run
     */
    public function verify(string $sql, string $input): VerificationResult
    {
        try {
            $result = $this->check->verify($sql, bin2hex($input));
        } catch (InfrastructureFailure $failure) {
            $this->coverage->record($sql, $input, new VerificationResult('infrastructure-failure', 'infrastructure', $failure->getMessage()));
            $this->coverage->flush();
            throw $failure;
        } catch (SyntaxFailure $failure) {
            $this->coverage->record($sql, $input, new VerificationResult('finding', 'syntax-failure', $failure->getMessage()));
            $this->coverage->flush();
            throw $failure;
        }
        $this->coverage->record($sql, $input, $result);
        return $result;
    }
}
